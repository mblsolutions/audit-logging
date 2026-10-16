<?php

namespace MBLSolutions\AuditLogging\Tests\Unit\Drivers;

use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use MBLSolutions\AuditLogging\Jobs\GenerateAuditLogJob;
use MBLSolutions\AuditLogging\Support\DTO\RequestResponseDTO;
use MBLSolutions\AuditLogging\Support\Enums\LogType;
use MBLSolutions\AuditLogging\Tests\LaravelTestCase;
use PHPUnit\Framework\Attributes\Test;

class CloudWatchDriverTest extends LaravelTestCase
{
    #[Test]
    public function the_job_sends_the_audit_event_to_cloudwatch(): void
    {
        $commands = [];
        $mock = new MockHandler();
        $mock->append(function (CommandInterface $command) use (&$commands) {
            $commands[] = $command;

            return new Result([]);
        });

        config([
            'audit-logging.system' => 'test-system',
            'audit-logging.drivers.cloudwatch' => [
                'driver' => \MBLSolutions\AuditLogging\Drivers\Log\CloudWatch::class,
                'group' => '/audit/test-system/staging',
                'region' => 'eu-west-2',
                'key' => 'test',
                'secret' => 'test',
                'handler' => $mock,
            ],
        ]);

        $dto = new RequestResponseDTO(LogType::API, Request::create('/api/balance', 'GET'), new Response('{"balance":10}', 200));

        (new GenerateAuditLogJob($dto, 'cloudwatch'))->handle();

        $this->assertCount(1, $commands);
        $this->assertSame($dto->occurredAtMs, $commands[0]['logEvents'][0]['timestamp']);

        $event = json_decode($commands[0]['logEvents'][0]['message'], true);

        $this->assertSame($dto->id, $event['event_id']);
        $this->assertSame('test-system', $event['system']);
        $this->assertSame('/api/balance', $event['uri']);
        $this->assertSame(['balance' => 10], $event['response_body']);
    }
}
