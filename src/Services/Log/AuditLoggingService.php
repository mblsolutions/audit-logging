<?php

namespace MBLSolutions\AuditLogging\Services\Log;

use Illuminate\Http\Request;
use MBLSolutions\AuditLogging\Drivers\Log\LogDriver;

class AuditLoggingService
{
    protected Request $request;

    protected $response;

    public function __construct(Request $request, $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    public function createLog(): void
    {
        $this->getLogDriver()->store();
    }

    public function getLogDriver(): LogDriver
    {
        $namespace = config('audit-logging.drivers.' . config('audit-logging.logger') . '.driver');

        return new $namespace($this->request, $this->response);
    }

}