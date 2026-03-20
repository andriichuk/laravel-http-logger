<?php

declare(strict_types=1);

namespace Andriichuk\HttpLogger\Listeners;

use Andriichuk\HttpLogger\Sanitizer;
use Illuminate\Container\Attributes\Config;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Listens for RequestHandled and logs request/response to the configured channel
 * when enabled, route and status filters match.
 */
final readonly class LogHttpRequest
{
    public function __construct(
        #[Config('http-logger')]
        private array $config,
        private Sanitizer $sanitizer,
    ) {}

    public function handle(RequestHandled $event): void
    {
        if (! $this->config['enabled'] || ! $event->request->is($this->config['routes'])) {
            return;
        }

        if (! $this->config['report']['info'] && $event->response->isInformational()) {
            return;
        }

        if (! $this->config['report']['success'] && $event->response->isSuccessful()) {
            return;
        }

        if (! $this->config['report']['redirect'] && $event->response->isRedirection()) {
            return;
        }

        if (! $this->config['report']['client_error'] && $event->response->isClientError()) {
            return;
        }

        if (! $this->config['report']['server_error'] && $event->response->isServerError()) {
            return;
        }

        $requestHeaders = $this->maskHeaders(
            $this->pickHeaders($event->request->headers->all(), $this->config['include_request_headers']),
            $this->config['sensitive_headers']
        );

        $responseHeaders = [];

        if (isset($event->response->headers)) {
            $responseHeaders = $this->maskHeaders(
                $this->pickHeaders($event->response->headers->all(), $this->config['include_response_headers']),
                $this->config['sensitive_headers']
            );
        }

        $sensitiveFields = $this->config['sensitive_fields'];
        $maxStringValueLength = $this->config['max_string_value_length'] ?? null;

        $requestBody = $this->sanitizer->sanitize(
            $this->getRequestInputWithoutFiles($event->request),
            $sensitiveFields,
            $maxStringValueLength ?? PHP_INT_MAX
        );

        $responseBody = $this->config['include_response']
            ? $this->formatResponseBodyForLogging($event->response, $sensitiveFields, $maxStringValueLength)
            : '[skipped]';

        $message = $this->config['message_prefix'].$event->request->method().' ';

        if ($this->config['include_host_in_message'] ?? false) {
            $message .= $event->request->getSchemeAndHttpHost();
        }

        $message .= $event->request->getPathInfo();

        $statusCode = $event->response->getStatusCode();
        $context = [
            'response_status_code' => $statusCode,
            'request_headers' => $requestHeaders,
            'response_headers' => $responseHeaders,
            'request_body' => $requestBody,
            'response_body' => $responseBody,
        ];

        if ($this->config['include_session_errors'] ?? false) {
            $sessionErrors = $this->getFlashedSessionErrors($event->request);
            
            if ($sessionErrors !== []) {
                $context['session_errors'] = $sessionErrors;
            }
        }

        if ($this->config['include_uploaded_files_metadata'] ?? true) {
            $filesMeta = $this->getUploadedFilesMetadata($event->request);

            if ($filesMeta !== []) {
                $context['uploaded_files'] = $filesMeta;
            }
        }

        $level = $this->getLogLevelForResponse($event->response);
        Log::channel($this->config['channel'])->log($level, $message, $context);
    }

    /**
     * PSR log level for the given response based on status category.
     */
    private function getLogLevelForResponse(HttpResponse $response): string
    {
        $map = $this->config['log_level_by_status'] ?? [
            'info' => 'info',
            'success' => 'info',
            'redirect' => 'info',
            'client_error' => 'warning',
            'server_error' => 'error',
        ];

        if ($response->isInformational()) {
            return $map['info'] ?? 'info';
        }
        if ($response->isSuccessful()) {
            return $map['success'] ?? 'info';
        }
        if ($response->isRedirection()) {
            return $map['redirect'] ?? 'info';
        }
        if ($response->isClientError()) {
            return $map['client_error'] ?? 'warning';
        }
        if ($response->isServerError()) {
            return $map['server_error'] ?? 'error';
        }

        return 'info';
    }

    /**
     * Get request input (query + body + attributes) without file inputs, so the
     * sanitizer is not given UploadedFile instances.
     *
     * @return array<string, mixed>
     */
    private function getRequestInputWithoutFiles(Request $request): array
    {
        $query = $request->query->all();
        $body = $request->request->all();
        $attributes = $request->attributes->all();

        return array_merge($query, $body, $attributes);
    }

    /**
     * Extract metadata from uploaded files (name, size, MIME type, extension).
     * Supports single and multiple file inputs; no file contents are included.
     *
     * @return array<int, array{name: string, original_name: string, size: int|null, mime_type: string|null, extension: string|null, error: int}>
     */
    private function getUploadedFilesMetadata(Request $request): array
    {
        $files = $request->allFiles();
        if ($files === []) {
            return [];
        }

        $meta = [];
        $this->collectFileMetadata($files, $meta);

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $files
     * @param  array<int, array{name: string, original_name: string, size: int|null, mime_type: string|null, extension: string|null, error: int}>  $meta
     */
    private function collectFileMetadata(array $files, array &$meta): void
    {
        foreach ($files as $inputName => $value) {
            if ($value instanceof UploadedFile || $value instanceof SymfonyUploadedFile) {
                $meta[] = [
                    'name' => $inputName,
                    'original_name' => $value->getClientOriginalName(),
                    'size' => $this->getUploadedFileSizeForLog($value),
                    'mime_type' => $this->getUploadedFileMimeTypeForLog($value),
                    'extension' => $value->getClientOriginalExtension() ?: null,
                    'error' => $value->getError(),
                ];

                continue;
            }

            if (is_array($value)) {
                $this->collectFileMetadata($value, $meta);
            }
        }
    }

    /**
     * Size from the temp path. After RequestHandled the file may already be moved or deleted,
     * so getSize() can throw; treat missing/unreadable files like unknown size.
     */
    private function getUploadedFileSizeForLog(UploadedFile|SymfonyUploadedFile $file): ?int
    {
        try {
            $size = $file->getSize();

            return $size ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * MIME from the filesystem. Fails for the same reasons as {@see getUploadedFileSizeForLog()}.
     */
    private function getUploadedFileMimeTypeForLog(UploadedFile|SymfonyUploadedFile $file): ?string
    {
        try {
            $mime = $file->getMimeType();

            return $mime !== '' ? $mime : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Read flashed validation errors from the session (read-only; does not consume flash).
     *
     * @return array<string, array<int, string>>
     */
    private function getFlashedSessionErrors(Request $request): array
    {
        if (! $request->hasSession()) {
            return [];
        }

        $session = $request->session();
        $errors = $session->get('errors');

        if ($errors === null) {
            return [];
        }

        if (is_object($errors) && is_callable([$errors, 'getMessages'])) {
            $messages = $errors->getMessages();

            return is_array($messages) ? $messages : [];
        }

        if (is_array($errors)) {
            return $errors;
        }

        return [];
    }

    /**
     * @param  array<string>  $sensitiveFields
     * @return array<string, mixed>|string
     */
    private function formatResponseBodyForLogging(HttpResponse $response, array $sensitiveFields, ?int $maxStringValueLength): array|string
    {
        $content = $response->getContent();

        if ($this->responseIsJson($response)) {
            return $this->sanitizer->sanitize(
                json_decode($content, true) ?? [],
                $sensitiveFields,
                $maxStringValueLength ?? PHP_INT_MAX
            );
        }

        if (! ($this->config['include_non_json_response'] ?? false)) {
            return '[skipped]';
        }

        $contentString = is_string($content) ? $content : '';

        if ($maxStringValueLength !== null && mb_strlen($contentString) > $maxStringValueLength) {
            return mb_substr($contentString, 0, $maxStringValueLength).'…';
        }

        return $contentString;
    }

    private function responseIsJson(HttpResponse $response): bool
    {
        $contentType = $response->headers->get('Content-Type');

        return $contentType !== null && str_contains(strtolower($contentType), 'application/json');
    }

    /**
     * @param  array<string, array<int, string>>  $all
     * @param  array<int, string>  $include  Header names to include, or ['*'] for all.
     * @return array<string, array<int, string>>
     */
    private function pickHeaders(array $all, array $include): array
    {
        if (empty($include)) {
            return [];
        }

        $lower = array_change_key_case($all, CASE_LOWER);

        if (in_array('*', $include, true)) {
            return $lower;
        }

        $includeLower = array_map('strtolower', $include);

        return Arr::only($lower, $includeLower);
    }

    /**
     * @param  array<string, array<int, string>>  $headers
     * @param  array<int, string>  $sensitive
     * @return array<string, array<int, string>|string>
     */
    private function maskHeaders(array $headers, array $sensitive): array
    {
        if (empty($sensitive)) {
            return $headers;
        }

        $sensitiveLower = array_map('strtolower', $sensitive);
        $out = [];

        foreach ($headers as $name => $value) {
            $out[$name] = in_array(strtolower($name), $sensitiveLower, true) ? '***' : $value;
        }

        return $out;
    }
}
