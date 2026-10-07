<?php

namespace MBLSolutions\AuditLogging\Tests\Unit\Jobs;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use MBLSolutions\AuditLogging\Jobs\GenerateAuditLogJob;
use MBLSolutions\AuditLogging\Support\DTO\RequestResponseDTO;
use MBLSolutions\AuditLogging\Support\Enums\LogType;
use MBLSolutions\AuditLogging\Tests\LaravelTestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class GenerateAuditLogJobTest extends LaravelTestCase
{
    #[Test]
    public function failed_logs_one_line_without_the_error_message(): void
    {
        Log::spy();

        $job = new GenerateAuditLogJob($this->dto(), 'database');
        $job->failed(new RuntimeException('insert into system_audit_logs (request_body) values ("alice@example.com")'));

        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context) => $message === 'Audit log could not be stored.'
            && $context['audit_event_id'] === $job->dto->id
            && $context['error'] === RuntimeException::class
            && ! str_contains(json_encode($context), 'alice@example.com'));
    }

    #[Test]
    public function failed_works_without_an_exception(): void
    {
        Log::spy();

        (new GenerateAuditLogJob($this->dto(), 'cloudwatch'))->failed(null);

        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context) => $context['error'] === null);
    }

    protected function dto(): RequestResponseDTO
    {
        return new RequestResponseDTO(LogType::API, Request::create('/api/orders', 'POST'), new Response('{}', 500));
    }
}
