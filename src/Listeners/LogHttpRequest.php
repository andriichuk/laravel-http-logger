<?php

declare(strict_types=1);

namespace Andriichuk\HttpLogger\Listeners;

use Andriichuk\HttpLogger\Sanitizer;
use Illuminate\Container\Attributes\Config;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
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
            $event->request->all(),
            $sensitiveFields,
            $maxStringValueLength ?? PHP_INT_MAX
        );

        $responseBody = $this->config['include_response']
            ? $this->formatResponseBodyForLogging($event->response, $sensitiveFields, $maxStringValueLength)
            : 'skipped';

        $message = $this->config['message_prefix'].$event->request->method().' ';

        if ($this->config['include_host_in_message'] ?? false) {
            $message .= $event->request->getSchemeAndHttpHost().' ';
        }

        $message .= $event->request->getPathInfo();

        $context = [
            'request_headers' => $requestHeaders,
            'response_headers' => $responseHeaders,
            'request' => $requestBody,
            'response' => $responseBody,
        ];

        if ($this->config['include_session_errors'] ?? false) {
            $sessionErrors = $this->getFlashedSessionErrors($event->request);
            if ($sessionErrors !== []) {
                $context['session_errors'] = $sessionErrors;
            }
        }

        Log::channel($this->config['channel'])->info($message, $context);
    }

    /**
     * Read flashed validation errors from the session (read-only; does not consume flash).
     *
     * @return array<string, array<int, string>>
     */
    private function getFlashedSessionErrors(mixed $request): array
    {
        if (! method_exists($request, 'hasSession') || ! $request->hasSession()) {
            return [];
        }

        $session = $request->session();
        $errors = $session->get('errors');

        if ($errors === null) {
            return [];
        }

        if (is_object($errors) && method_exists($errors, 'getMessages')) {
            return $errors->getMessages();
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
