<?php

namespace MBLSolutions\AuditLogging\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use MBLSolutions\AuditLogging\Jobs\CreateAuditLog;

class AuditLogging
{

    public function handle(Request $request, Closure  $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        if ($this->isAuditLoggingEnabled()) {
            CreateAuditLog::dispatch($request, $response);
        }
    }

    protected function isAuditLoggingEnabled(): bool
    {
        return config('audit-logging.enabled') === true;
    }

}