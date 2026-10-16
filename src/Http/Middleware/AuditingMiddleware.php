<?php

namespace MBLSolutions\AuditLogging\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use MBLSolutions\AuditLogging\Jobs\GenerateAuditLogJob;
use MBLSolutions\AuditLogging\Services\Log\AuditLoggingService;
use MBLSolutions\AuditLogging\Support\DTO\RequestResponseDTO;
use Throwable;

abstract class AuditingMiddleware
{

    public function log(string $type, Request $request, $response): void
    {
        // On Vapor terminate() runs before the response is sent, so an audit error must never break the request.
        try {
            if ($this->isAuditLoggingEnabled()) {
                $dto = new RequestResponseDTO($type, $request, $response, Auth::guard(config('audit-logging.auth_guard'))->user());

                // One job per driver, so a retry on one driver doesn't write twice to the other.
                foreach (AuditLoggingService::driverNames() as $driver) {
                    GenerateAuditLogJob::dispatch($dto, $driver)->onQueue(config('audit-logging.queue'));
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected function isAuditLoggingEnabled(): bool
    {
        return filter_var(config('audit-logging.enabled'), FILTER_VALIDATE_BOOLEAN);
    }

}
