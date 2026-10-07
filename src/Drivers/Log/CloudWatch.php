<?php

namespace MBLSolutions\AuditLogging\Drivers\Log;

use MBLSolutions\AuditLogging\CloudWatch\CloudWatchWriter;
use MBLSolutions\AuditLogging\Support\AuditEvent;
use MBLSolutions\AuditLogging\Support\DTO\RequestResponseDTO;

class CloudWatch implements LogDriver
{
    public RequestResponseDTO $dto;

    protected array $config;

    public function __construct(RequestResponseDTO $dto)
    {
        $this->dto = $dto;
        $this->config = config('audit-logging.drivers.cloudwatch', []);
    }

    public function store(): bool
    {
        CloudWatchWriter::fromConfig($this->config)->write(AuditEvent::fromDto($this->dto), $this->dto->occurredAtMs);

        return true;
    }

}
