<?php

namespace MBLSolutions\AuditLogging\Tests\Unit\Services;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use MBLSolutions\AuditLogging\Services\Log\AuditLoggingService;
use MBLSolutions\AuditLogging\Support\DTO\RequestResponseDTO;
use MBLSolutions\AuditLogging\Support\Enums\LogType;
use MBLSolutions\AuditLogging\Tests\LaravelTestCase;

class LoggingServiceTest extends LaravelTestCase
{

    /** @test **/
    public function can_create_audit_logging_service(): void
    {
        $service = new AuditLoggingService(new RequestResponseDTO(LogType::WEB, new Request([]), new Response([])));

        $this->assertInstanceOf(AuditLoggingService::class, $service);
    }

}