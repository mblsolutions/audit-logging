<?php

namespace MBLSolutions\AuditLogging\CloudWatch;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\CloudWatchLogs\Exception\CloudWatchLogsException;
use Aws\Credentials\CredentialProvider;
use Aws\Sts\StsClient;
use RuntimeException;

class CloudWatchWriter
{
    /** CloudWatch hard limit per event is 1 MB, including 26 bytes of overhead. */
    public const MAX_EVENT_BYTES = 1048550;

    /** Used when max_event_bytes is missing, empty or not a positive number. */
    public const DEFAULT_EVENT_BYTES = 262144;

    /** PutLogEvents limits per call. */
    public const MAX_BATCH_EVENTS = 10000;

    public const MAX_BATCH_BYTES = 1048576;

    /** Largest parts of an audit event, dropped in this order when the event is too big. */
    protected const LARGE_KEYS = ['response_body', 'request_body', 'response_headers', 'request_headers'];

    /** @var array<string, CloudWatchLogsClient> */
    protected static array $clients = [];

    protected CloudWatchLogsClient $client;

    protected string $group;

    protected ?string $stream;

    protected int $maxEventBytes;

    public function __construct(CloudWatchLogsClient $client, string $group, ?string $stream = null, ?int $maxEventBytes = null)
    {
        $this->client = $client;
        $this->group = $group;
        $this->stream = $stream;
        $this->maxEventBytes = min($maxEventBytes ?? self::MAX_EVENT_BYTES, self::MAX_EVENT_BYTES);
    }

    /**
     * Build a writer from the "cloudwatch" driver config.
     *
     * @param array{group?: string, stream?: string, region?: string, key?: string, secret?: string, token?: string, role_arn?: string, max_event_bytes?: int} $config
     */
    public static function fromConfig(array $config): static
    {
        if (empty($config['group'])) {
            throw new RuntimeException('Audit logging: CloudWatch log group is not configured (AUDIT_LOGGING_CLOUDWATCH_GROUP).');
        }

        $clientConfig = static::clientConfig($config);
        $cacheKey = md5(serialize([
            $clientConfig['region'],
            $config['key'] ?? null,
            $config['role_arn'] ?? null,
            isset($config['handler']) ? spl_object_id($config['handler']) : null,
        ]));

        // Reuse the client between queue jobs in the same Lambda container.
        static::$clients[$cacheKey] ??= new CloudWatchLogsClient($clientConfig);

        // An empty env value would be 0, which strips the bodies from every event.
        $maxEventBytes = (int) ($config['max_event_bytes'] ?? 0);

        return new static(
            static::$clients[$cacheKey],
            $config['group'],
            $config['stream'] ?? null,
            $maxEventBytes > 0 ? $maxEventBytes : self::DEFAULT_EVENT_BYTES,
        );
    }

    /**
     * Send one audit event. The log stream is created on first use.
     */
    public function write(array $event, ?int $timestampMs = null): void
    {
        $this->writeMany([$event], $timestampMs);
    }

    /**
     * Send events that share one timestamp (e.g. one TSR request for many links).
     * Split into calls of at most 10,000 events and 1 MB.
     */
    public function writeMany(array $events, ?int $timestampMs = null): void
    {
        $timestampMs ??= (int) floor(microtime(true) * 1000);
        $stream = $this->streamName($timestampMs);

        $batch = [];
        $batchBytes = 0;

        foreach ($events as $event) {
            $message = $this->encode($event);
            $bytes = strlen($message) + 26;

            if ($batch && (count($batch) >= self::MAX_BATCH_EVENTS || $batchBytes + $bytes > self::MAX_BATCH_BYTES)) {
                $this->put($stream, $batch);

                $batch = [];
                $batchBytes = 0;
            }

            $batch[] = ['timestamp' => $timestampMs, 'message' => $message];
            $batchBytes += $bytes;
        }

        if ($batch) {
            $this->put($stream, $batch);
        }
    }

    protected function put(string $stream, array $logEvents): void
    {
        $payload = [
            'logGroupName' => $this->group,
            'logStreamName' => $stream,
            'logEvents' => $logEvents,
        ];

        try {
            $result = $this->client->putLogEvents($payload);
        } catch (CloudWatchLogsException $exception) {
            if ($exception->getAwsErrorCode() !== 'ResourceNotFoundException') {
                throw $exception;
            }

            $this->createStream($stream);

            $result = $this->client->putLogEvents($payload);
        }

        $rejected = $result->get('rejectedLogEventsInfo');

        if (! empty($rejected)) {
            throw new RuntimeException('Audit logging: CloudWatch rejected the event: ' . json_encode($rejected));
        }
    }

    public function encode(array $event): string
    {
        $message = $this->toJson($event);

        if (strlen($message) <= $this->maxEventBytes) {
            return $message;
        }

        foreach (self::LARGE_KEYS as $key) {
            if (! isset($event[$key])) {
                continue;
            }

            $event[$key] = '[removed: event too large]';
            $event['truncated'] = true;

            $message = $this->toJson($event);

            if (strlen($message) <= $this->maxEventBytes) {
                return $message;
            }
        }

        // Last resort: keep only the scalar fields (ids, route, status, etc.).
        return $this->toJson(array_filter($event, 'is_scalar') + ['truncated' => true]);
    }

    protected function streamName(int $timestampMs): string
    {
        return $this->stream ?: gmdate('Y/m/d', intdiv($timestampMs, 1000));
    }

    protected function createStream(string $stream): void
    {
        try {
            $this->client->createLogStream([
                'logGroupName' => $this->group,
                'logStreamName' => $stream,
            ]);
        } catch (CloudWatchLogsException $exception) {
            // Another worker created it first.
            if ($exception->getAwsErrorCode() !== 'ResourceAlreadyExistsException') {
                throw $exception;
            }
        }
    }

    protected function toJson(array $event): string
    {
        return json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }

    protected static function clientConfig(array $config): array
    {
        $clientConfig = [
            'version' => '2014-03-28',
            'region' => $config['region'] ?? 'eu-west-2',
            'http' => ['connect_timeout' => 5, 'timeout' => 10],
        ];

        // Only set in tests (Aws\MockHandler).
        if (isset($config['handler'])) {
            $clientConfig['handler'] = $config['handler'];
        }

        // Without a key the AWS SDK uses the default chain (the Lambda role on Vapor).
        if (! empty($config['key']) && ! empty($config['secret'])) {
            $clientConfig['credentials'] = [
                'key' => $config['key'],
                'secret' => $config['secret'],
                'token' => $config['token'] ?? null,
            ];
        }

        // Log group in another AWS account.
        if (! empty($config['role_arn'])) {
            $stsConfig = ['version' => '2011-06-15', 'region' => $clientConfig['region']];

            if (isset($clientConfig['credentials'])) {
                $stsConfig['credentials'] = $clientConfig['credentials'];
            }

            $clientConfig['credentials'] = CredentialProvider::memoize(CredentialProvider::assumeRole([
                'client' => new StsClient($stsConfig),
                'assume_role_params' => [
                    'RoleArn' => $config['role_arn'],
                    'RoleSessionName' => 'audit-logging',
                ],
            ]));
        }

        return $clientConfig;
    }
}
