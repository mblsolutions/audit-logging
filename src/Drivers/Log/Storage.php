<?php

namespace MBLSolutions\AuditLogging\Drivers\Log;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Storage implements LogDriver
{
    protected Request $request;

    protected $response;

    protected Logger $log;

    private array $config;

    public function __construct(Request $request, $response)
    {
        $this->request = $request;
        $this->response = $response;

        $this->config = config('audit-logging.drivers.storage');

        $this->log = new Logger('audit-logging');
    }

    public function store(): bool
    {
        $this->log->pushHandler(new StreamHandler($this->config['path'] . '/audit_' . Carbon::now()->format('Ymd') . '.log', Logger::INFO));

        $this->log->info($this->request->getContent(), $this->getContext());

        return true;
    }

    public function getContext(): array
    {
        return [
            'method' => $this->request->getMethod(),
            'uri' => $this->request->getRequestUri(),
            'user_id' => optional(Auth::user())->getKey(),
        ];
    }

}