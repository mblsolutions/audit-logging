<?php

namespace MBLSolutions\AuditLogging\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use MBLSolutions\AuditLogging\Jobs\GenerateAuditLogJob;
use MBLSolutions\AuditLogging\Services\Log\AuditLoggingService;
use MBLSolutions\AuditLogging\Support\DTO\RequestResponseDTO;

abstract class AuditingMiddleware
{

    public function log(string $type, Request $request, $response): void
    {
        if ($this->isAuditLoggingEnabled()) {
            $dto = new RequestResponseDTO($type, $request, $response, Auth::guard(config('audit-logging.auth_guard'))->user());

            // One job per driver, so a retry on one driver doesn't write twice to the other.
            foreach (AuditLoggingService::driverNames() as $driver) {
                GenerateAuditLogJob::dispatch($dto, $driver)->onQueue(config('audit-logging.queue'));
            }
        }
    }

    protected function isAuditLoggingEnabled(): bool
    {
        return config('audit-logging.enabled') === true;
    }

}
