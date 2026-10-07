<?php

namespace MBLSolutions\AuditLogging\Tests\Unit\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Queue;
use MBLSolutions\AuditLogging\Http\Middleware\ApiAuditLogging;
use MBLSolutions\AuditLogging\Jobs\GenerateAuditLogJob;
use MBLSolutions\AuditLogging\Tests\LaravelTestCase;
use PHPUnit\Framework\Attributes\Test;

class AuditingMiddlewareTest extends LaravelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    #[Test]
    public function it_dispatches_one_job_for_the_configured_driver(): void
    {
        config(['audit-logging.enabled' => true, 'audit-logging.logger' => 'database']);

        $this->terminate();

        Queue::assertPushed(GenerateAuditLogJob::class, 1);
        Queue::assertPushed(GenerateAuditLogJob::class, fn (GenerateAuditLogJob $job) => $job->driver === 'database');
    }

    #[Test]
    public function the_stack_logger_dispatches_one_job_per_driver(): void
    {
        config([
            'audit-logging.enabled' => true,
            'audit-logging.logger' => 'stack',
            'audit-logging.drivers.stack.drivers' => ['database', 'cloudwatch'],
            'audit-logging.queue' => 'audit',
        ]);

        $this->terminate();

        Queue::assertPushed(GenerateAuditLogJob::class, 2);
        Queue::assertPushedOn('audit', GenerateAuditLogJob::class, fn (GenerateAuditLogJob $job) => $job->driver === 'database');
        Queue::assertPushedOn('audit', GenerateAuditLogJob::class, fn (GenerateAuditLogJob $job) => $job->driver === 'cloudwatch');
    }

    #[Test]
    public function the_stack_logger_trims_driver_names(): void
    {
        // AUDIT_LOGGING_STACK="database, cloudwatch"
        config([
            'audit-logging.enabled' => true,
            'audit-logging.logger' => 'stack',
            'audit-logging.drivers.stack.drivers' => ['database', ' cloudwatch', ''],
        ]);

        $this->terminate();

        Queue::assertPushed(GenerateAuditLogJob::class, 2);
        Queue::assertPushed(GenerateAuditLogJob::class, fn (GenerateAuditLogJob $job) => $job->driver === 'cloudwatch');
    }

    #[Test]
    public function the_stack_logger_falls_back_to_database_and_cloudwatch_on_old_configs(): void
    {
        config(['audit-logging.enabled' => true, 'audit-logging.logger' => 'stack']);
        config()->offsetUnset('audit-logging.drivers.stack');

        $this->terminate();

        Queue::assertPushed(GenerateAuditLogJob::class, 2);
    }

    #[Test]
    public function it_accepts_string_values_for_the_enabled_flag(): void
    {
        config(['audit-logging.enabled' => '1', 'audit-logging.logger' => 'database']);

        $this->terminate();

        Queue::assertPushed(GenerateAuditLogJob::class, 1);
    }

    #[Test]
    public function nothing_is_dispatched_when_disabled(): void
    {
        config(['audit-logging.enabled' => false]);

        $this->terminate();

        Queue::assertNothingPushed();
    }

    protected function terminate(): void
    {
        (new ApiAuditLogging())->terminate(Request::create('/api/test', 'POST'), new Response('{}', 200));
    }
}
