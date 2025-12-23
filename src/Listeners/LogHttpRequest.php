<?php

declare(strict_types=1);

namespace Andriichuk\HttpLogger\Listeners;

use Andriichuk\HttpLogger\Sanitizer;
use Illuminate\Container\Attributes\Config;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

final readonly class LogHttpRequest
{
    public function __construct(
        #[Config('http-logger')]
        private array $config,
        private Sanitizer $sanitizer,
    ) {}

    public function handle(RequestHandled $event): void
    {
        if (!$this->config['enabled'] || !$event->request->is($this->config['routes'])) {
            return;
        }

        if (!$this->config['report']['info'] && $event->response->isInformational()) {
            return;
        }

        if (!$this->config['report']['success'] && $event->response->isSuccessful()) {
            return;
        }

        if (!$this->config['report']['client_error'] && $event->response->isClientError()) {
            return;
        }

        if (!$this->config['report']['server_error'] && $event->response->isServerError()) {
            return;
        }

        Log::channel($this->config['channel'])->info(
            message: $this->config['message_prefix'] . $event->request->method() . ' ' . $event->request->getPathInfo(),
            context: [
                'headers' => Arr::only($event->request->headers->all(), $this->config['include_headers']),
                'request' => $this->sanitizer->sanitize($event->request->all()),
                'response' => $this->config['include_response']
                    ? $this->sanitizer->sanitize(json_decode($event->response->getContent(), true) ?? [])
                    : 'skipped',
            ],
        );
    }


}
