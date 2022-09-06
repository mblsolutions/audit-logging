<?php

namespace MBLSolutions\AuditLogging\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use MBLSolutions\AuditLogging\Support\Enums\LogType;

class ApiAuditLogging extends AuditingMiddleware
{

    public function handle(Request $request, Closure  $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        $this->log(LogType::API, $request, $response);
    }


}