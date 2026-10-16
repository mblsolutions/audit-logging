<?php

namespace MBLSolutions\AuditLogging\Support\DTO;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use MBLSolutions\AuditLogging\Support\Concerns\ShouldMaskSensitiveData;

class RequestResponseDTO
{
    use ShouldMaskSensitiveData;

    /** Always masked in logged headers, whatever the app's protected_keys say. */
    public const SENSITIVE_HEADERS = [
        'authorization',
        // Symfony copies Basic/Digest Authorization headers into these.
        'php-auth-pw',
        'php-auth-digest',
        'proxy-authorization',
        'cookie',
        'set-cookie',
        'x-xsrf-token',
        'x-csrf-token',
        'x-api-key',
    ];

    public string $id;
    public ?string $reference = null;
    public ?string $method = null;
    public ?string $uri = null;
    public ?int $status = null;
    public string $type;
    public ?string $remoteAddress;
    public ?string $auth = null;
    public ?string $requestHeaders = null;
    public ?string $requestBody = null;
    public ?string $requestFingerprint = null;
    public ?string $responseHeader = null;
    public ?string $responseBody = null;
    public ?string $responseFingerprint = null;
    public string $dateTime;
    public ?int $userId = null;
    public ?int $occurredAtMs = null;
    public ?string $route = null;
    public ?int $durationMs = null;
    public ?string $userAgent = null;
    public ?string $requestId = null;
    public ?string $traceId = null;
    public ?string $awsRequestId = null;
    public array $context = [];

    public function __construct(string $type, Request $request, $response, $auth = null)
    {
        $this->occurredAtMs = (int) floor(microtime(true) * 1000);
        $this->id = Str::uuid();
        $this->reference = $this->handleRouteParameters($request);
        $this->method = $request->getMethod();
        $this->uri = $this->handleUri($request);
        $this->status = $response->getStatusCode();
        $this->type = $type;
        $this->remoteAddress = $request->ip();
        $this->auth = $this->handleAuthData($auth);
        $this->requestHeaders = config('audit-logging.loggable.request_header') ? $this->convertHeadersToJson($request->headers?->all()) : null;
        $this->requestBody = config('audit-logging.loggable.request_body') ? $this->handleBodyIfJson($request) : null;
        $this->requestFingerprint = $this->generateFingerprint($request->headers, $request->getContent());
        $this->responseHeader = config('audit-logging.loggable.response_header') ? $this->convertHeadersToJson($response->headers?->all()) : null;
        $this->responseBody = config('audit-logging.loggable.response_body') ? $this->handleBodyIfJson($response) : null;
        $this->responseFingerprint = $this->generateFingerprint($response->headers, $response->getContent());
        $this->dateTime = Carbon::now()->toDateTimeString();
        $this->userId = optional($auth)->getKey();
        $this->route = $this->handleRouteName($request);
        $this->durationMs = $this->handleDuration($request);
        $this->userAgent = $this->limitHeader($request->userAgent(), 512);
        $this->requestId = $this->limitHeader($request->headers->get('X-Request-Id'), 128);
        $this->traceId = $this->handleTraceId();
        $this->awsRequestId = $_ENV['AWS_REQUEST_ID'] ?? $_SERVER['AWS_REQUEST_ID'] ?? null;
        $this->context = $this->handleContext($request, $response, $auth);
    }

    /**
     * The request URI with protected keys masked in the query string and in route values.
     */
    public function handleUri(Request $request): string
    {
        $protected = config('audit-logging.protected_keys', []);
        [$path, $query] = array_pad(explode('?', $request->getRequestUri(), 2), 2, null);

        $path = $this->maskPathSegments($path, $this->protectedRouteValues($request, $protected));

        return $query === null ? $path : $path . '?' . $this->maskQueryString($query, $protected);
    }

    protected function protectedRouteValues(Request $request, array $protected): array
    {
        $protected = array_map(fn ($key) => strtolower((string) $key), $protected);
        $values = [];

        foreach ($request->route()?->originalParameters() ?? [] as $name => $value) {
            if (is_string($value) && $value !== '' && in_array(strtolower((string) $name), $protected, true)) {
                $values[] = $value;
            }
        }

        return $values;
    }

    public function handleRouteName(Request $request): ?string
    {
        $route = $request->route();

        if (! $route) {
            return null;
        }

        return $route->getName() ?: $route->uri();
    }

    public function handleDuration(Request $request): ?int
    {
        $start = defined('LARAVEL_START') ? LARAVEL_START : $request->server('REQUEST_TIME_FLOAT');

        return $start ? (int) round((microtime(true) - (float) $start) * 1000) : null;
    }

    public function handleTraceId(): ?string
    {
        if (class_exists(\OpenTelemetry\API\Trace\Span::class)) {
            $context = \OpenTelemetry\API\Trace\Span::getCurrent()->getContext();

            if ($context->isValid()) {
                return $context->getTraceId();
            }
        }

        // keepsuit/laravel-opentelemetry ends the request span before terminate(), but keeps the id in the log context.
        $shared = Log::sharedContext();
        $traceId = $shared['traceid'] ?? $shared['trace_id'] ?? null;

        return is_string($traceId) ? $traceId : null;
    }

    public function handleContext(Request $request, $response, $auth = null): array
    {
        $resolver = config('audit-logging.context_resolver');

        if (! $resolver) {
            return [];
        }

        try {
            $context = app($resolver)->resolve($request, $response, $auth);

            return $this->maskArrayData(array_filter($context, 'is_scalar'), config('audit-logging.protected_keys', []));
        } catch (\Throwable $exception) {
            // Never break the request because of audit context.
            report($exception);
        }

        return [];
    }

    protected function limitHeader(?string $value, int $length): ?string
    {
        return $value === null || $value === '' ? null : Str::limit($value, $length, '');
    }

    public function handleBodyIfJson($data): ?string
    {
        $content = $data->getContent();

        if (config('audit-logging.json_body_only') !== true) {
            return $this->convertDataToJson($content);
        }

        if ($this->isJson($content)) {
            return $this->convertDataToJson($content);
        }

        return null;
    }

    public function convertDataToJson($data = null): ?string
    {
        try {
            if ($data !== null) {
                $sanitised = $this->maskSensitiveData($data, config('audit-logging.protected_keys', []));

                if (is_string($sanitised)) {
                    $sanitised = Str::limit($sanitised, config('audit-logging.max_loggable_length', 10024));
                }

                return json_encode($sanitised, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            }
        } catch (JsonException $e) {}

        return null;
    }

    public function convertHeadersToJson(?array $headers): ?string
    {
        if ($headers === null) {
            return null;
        }

        $protected = array_merge(config('audit-logging.protected_keys', []), self::SENSITIVE_HEADERS);

        return $this->convertDataToJson($this->maskArrayData($headers, $protected));
    }

    public function generateFingerprint($a = null, $b = null): ?string
    {
        if ($a !== null || $b !== null) {
            return md5($a . $b);
        }

        return null;
    }

    public function handleRouteParameters(Request $request): ?string
    {
        $route = $request->route();

        if ($route) {
            return $this->convertDataToJson($route->originalParameters());
        }

        return null;
    }

    public function handleAuthData($auth = null): ?string
    {
        if ($auth) {
            return $this->convertDataToJson([
                'id' => optional($auth)->getKey(),
                'name' => optional($auth)->getAttribute('name'),
                'email' => optional($auth)->getAttribute('email'),
            ]);
        }

        return null;
    }

}