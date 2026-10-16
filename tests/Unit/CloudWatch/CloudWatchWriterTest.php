<?php

namespace MBLSolutions\AuditLogging\Tests\Unit\CloudWatch;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\CloudWatchLogs\Exception\CloudWatchLogsException;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use MBLSolutions\AuditLogging\CloudWatch\CloudWatchWriter;
use MBLSolutions\AuditLogging\Tests\LaravelTestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class CloudWatchWriterTest extends LaravelTestCase
{
    /** @var array<int, CommandInterface> */
    protected array $commands = [];

    protected MockHandler $mock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->commands = [];
        $this->mock = new MockHandler();
    }

    #[Test]
    public function it_writes_one_event_to_a_daily_stream(): void
    {
        $this->respond(new Result([]));

        $timestamp = 1790000000123;

        $this->writer()->write(['event_id' => 'abc', 'status' => 200], $timestamp);

        $this->assertCount(1, $this->commands);
        $this->assertSame('PutLogEvents', $this->commands[0]->getName());
        $this->assertSame('/audit/test-system/production', $this->commands[0]['logGroupName']);
        $this->assertSame(gmdate('Y/m/d', intdiv($timestamp, 1000)), $this->commands[0]['logStreamName']);
        $this->assertSame($timestamp, $this->commands[0]['logEvents'][0]['timestamp']);
        $this->assertSame(['event_id' => 'abc', 'status' => 200], json_decode($this->commands[0]['logEvents'][0]['message'], true));
    }

    #[Test]
    public function it_uses_a_fixed_stream_when_configured(): void
    {
        $this->respond(new Result([]));

        $this->writer('audit')->write(['event_id' => 'abc']);

        $this->assertSame('audit', $this->commands[0]['logStreamName']);
    }

    #[Test]
    public function it_creates_the_stream_when_it_does_not_exist(): void
    {
        $this->respond('ResourceNotFoundException');
        $this->respond(new Result([]));
        $this->respond(new Result([]));

        $this->writer()->write(['event_id' => 'abc']);

        $this->assertSame(['PutLogEvents', 'CreateLogStream', 'PutLogEvents'], $this->commandNames());
    }

    #[Test]
    public function it_ignores_a_stream_created_by_another_worker(): void
    {
        $this->respond('ResourceNotFoundException');
        $this->respond('ResourceAlreadyExistsException');
        $this->respond(new Result([]));

        $this->writer()->write(['event_id' => 'abc']);

        $this->assertSame(['PutLogEvents', 'CreateLogStream', 'PutLogEvents'], $this->commandNames());
    }

    #[Test]
    public function it_rethrows_other_aws_errors(): void
    {
        $this->respond('AccessDeniedException');

        $this->expectException(CloudWatchLogsException::class);

        $this->writer()->write(['event_id' => 'abc']);
    }

    #[Test]
    public function it_fails_when_cloudwatch_rejects_the_event(): void
    {
        $this->respond(new Result(['rejectedLogEventsInfo' => ['tooOldLogEventEndIndex' => 0]]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('rejected');

        $this->writer()->write(['event_id' => 'abc']);
    }

    #[Test]
    public function it_drops_bodies_when_the_event_is_too_large(): void
    {
        $writer = new CloudWatchWriter($this->client(), '/audit/test', null, 2000);

        $message = $writer->encode([
            'event_id' => 'abc',
            'user_id' => 5,
            'request_body' => ['a' => 'small'],
            'response_body' => ['data' => str_repeat('x', 5000)],
        ]);

        $event = json_decode($message, true);

        $this->assertLessThanOrEqual(2000, strlen($message));
        $this->assertSame('abc', $event['event_id']);
        $this->assertSame(5, $event['user_id']);
        $this->assertSame(['a' => 'small'], $event['request_body']);
        $this->assertSame('[removed: event too large]', $event['response_body']);
        $this->assertTrue($event['truncated']);
    }

    #[Test]
    public function it_splits_many_events_into_calls_under_1_mb(): void
    {
        $this->respond(new Result([]));
        $this->respond(new Result([]));

        // 3 events of ~400 KB: two fit in the first call, the third goes in a second call.
        $events = array_map(fn ($i) => ['reference' => "link-{$i}", 'request_body' => str_repeat('x', 400000)], [1, 2, 3]);

        $this->writer()->writeMany($events, 1790000000123);

        $this->assertSame(['PutLogEvents', 'PutLogEvents'], $this->commandNames());
        $this->assertCount(2, $this->commands[0]['logEvents']);
        $this->assertCount(1, $this->commands[1]['logEvents']);
        $this->assertSame('link-3', json_decode($this->commands[1]['logEvents'][0]['message'], true)['reference']);
    }

    #[Test]
    public function it_needs_a_log_group(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AUDIT_LOGGING_CLOUDWATCH_GROUP');

        CloudWatchWriter::fromConfig(['group' => null]);
    }

    #[Test]
    public function it_builds_the_client_from_config(): void
    {
        $this->respond(new Result([]));

        $writer = CloudWatchWriter::fromConfig([
            'group' => '/audit/test-system/staging',
            'region' => 'eu-west-2',
            'key' => 'test',
            'secret' => 'test',
            'handler' => $this->mock,
        ]);

        $writer->write(['event_id' => 'abc']);

        $this->assertSame('/audit/test-system/staging', $this->commands[0]['logGroupName']);
    }

    #[Test]
    public function an_empty_max_event_bytes_falls_back_to_the_default(): void
    {
        $this->respond(new Result([]));

        // AUDIT_LOGGING_CLOUDWATCH_MAX_EVENT_BYTES=
        $writer = CloudWatchWriter::fromConfig([
            'group' => '/audit/test-system/staging',
            'region' => 'eu-west-2',
            'key' => 'test',
            'secret' => 'test',
            'max_event_bytes' => '',
            'handler' => $this->mock,
        ]);

        $writer->write(['event_id' => 'abc', 'response_body' => ['ok' => true]]);

        $event = json_decode($this->commands[0]['logEvents'][0]['message'], true);

        $this->assertSame(['ok' => true], $event['response_body']);
        $this->assertArrayNotHasKey('truncated', $event);
    }

    protected function writer(?string $stream = null): CloudWatchWriter
    {
        return new CloudWatchWriter($this->client(), '/audit/test-system/production', $stream);
    }

    protected function client(): CloudWatchLogsClient
    {
        return new CloudWatchLogsClient([
            'version' => '2014-03-28',
            'region' => 'eu-west-2',
            'credentials' => ['key' => 'test', 'secret' => 'test'],
            'handler' => $this->mock,
        ]);
    }

    /**
     * Queue a result, or an AWS error code to throw.
     */
    protected function respond(Result|string $response): void
    {
        $this->mock->append(function (CommandInterface $command) use ($response) {
            $this->commands[] = $command;

            if (is_string($response)) {
                return new CloudWatchLogsException($response, $command, ['code' => $response]);
            }

            return $response;
        });
    }

    protected function commandNames(): array
    {
        return array_map(fn (CommandInterface $command) => $command->getName(), $this->commands);
    }
}
