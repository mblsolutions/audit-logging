<?php

namespace MBLSolutions\AuditLogging\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use MBLSolutions\AuditLogging\Services\Log\AuditLoggingService;

class AuditLogging
{

    public function handle(Request $request, Closure  $next)
    {
        if ($this->isAuditLoggingEnabled()) {
        }

        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        if ($this->isAuditLoggingEnabled()) {
            (new AuditLoggingService($request, $response))->createLog();
        }
    }

    protected function isAuditLoggingEnabled(): bool
    {
        return config('audit-logging.enabled') === true;
    }

}