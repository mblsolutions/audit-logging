<?php

namespace MBLSolutions\AuditLogging\Drivers\Log;

use Illuminate\Support\Carbon;
use MBLSolutions\AuditLogging\Support\DTO\RequestResponseDTO;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class File implements LogDriver
{
    public RequestResponseDTO $dto;

    protected Logger $log;

    protected array $config;

    public function __construct(RequestResponseDTO $dto)
    {
        $this->dto = $dto;
        $this->config = config('audit-logging.drivers.file');
        $this->log = new Logger('audit-logging');
    }

    public function store(): bool
    {
        $this->log->pushHandler(new StreamHandler($this->config['path'] . '/audit_' . Carbon::now()->format('Ymd') . '.log', Logger::INFO));

        $this->log->info($this->dto->requestBody, $this->getContext('Request'));
        $this->log->info($this->dto->responseBody, $this->getContext('Response'));

        return true;
    }

    public function getContext(string $key): array
    {
        return [
            'key' => $key,
            'type' => $this->dto->type,
            'method' => $this->dto->method,
            'uri' => $this->dto->uri,
            'user_id' => $this->dto->auth,
        ];
    }

}