<?php

namespace MBLSolutions\AuditLogging\Support;

use Illuminate\Support\Str;
use MBLSolutions\AuditLogging\Support\DTO\RequestResponseDTO;

class AuditEvent
{
    public const SCHEMA_VERSION = 1;

    /**
     * Flat JSON event for CloudWatch. Search fields come first, bodies last.
     */
    public static function fromDto(RequestResponseDTO $dto): array
    {
        $event = [
            'schema_version' => self::SCHEMA_VERSION,
            'event_id' => $dto->id,
            'occurred_at' => self::isoTime($dto->occurredAtMs),
            'system' => config('audit-logging.system') ?? Str::slug((string) config('app.name')),
            'env' => config('app.env'),
            'type' => $dto->type,
            'user_id' => $dto->userId,
            'method' => $dto->method,
            'uri' => $dto->uri,
            'route' => $dto->route,
            'status' => $dto->status,
            'duration_ms' => $dto->durationMs,
            'ip' => $dto->remoteAddress,
            'user_agent' => $dto->userAgent,
            'request_id' => $dto->requestId,
            'trace_id' => $dto->traceId,
            'aws_request_id' => $dto->awsRequestId,
        ];

        // App specific ids (account_id, order_id, ...). Core fields win on a name clash.
        foreach ($dto->context as $key => $value) {
            if (! array_key_exists($key, $event)) {
                $event[$key] = $value;
            }
        }

        $event['reference'] = self::decode($dto->reference);
        $event['auth'] = self::decode($dto->auth);
        $event['request_fingerprint'] = $dto->requestFingerprint;
        $event['response_fingerprint'] = $dto->responseFingerprint;
        $event['request_headers'] = self::decode($dto->requestHeaders);
        $event['response_headers'] = self::decode($dto->responseHeader);

        self::addBody($event, 'request_body', $dto->requestBody);
        self::addBody($event, 'response_body', $dto->responseBody);

        return $event;
    }

    /**
     * DTO values are stored as JSON text, bodies twice (JSON text inside a JSON string).
     * CloudWatch needs real objects, otherwise Logs Insights can't read the fields.
     */
    public static function decode(?string $stored): mixed
    {
        if ($stored === null) {
            return null;
        }

        $value = json_decode($stored, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $stored;
        }

        if (is_string($value)) {
            $inner = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($inner)) {
                return $inner;
            }
        }

        return $value;
    }

    protected static function addBody(array &$event, string $key, ?string $stored): void
    {
        $body = self::decode($stored);

        $event[$key] = $body;

        // Body was cut at max_loggable_length, so it is no longer valid JSON.
        if (is_string($body) && in_array(substr(ltrim($body), 0, 1), ['{', '['], true)) {
            $event[$key . '_truncated'] = true;
        }
    }

    protected static function isoTime(?int $timestampMs): string
    {
        $timestampMs ??= (int) floor(microtime(true) * 1000);

        return gmdate('Y-m-d\TH:i:s', intdiv($timestampMs, 1000)) . sprintf('.%03dZ', $timestampMs % 1000);
    }
}
