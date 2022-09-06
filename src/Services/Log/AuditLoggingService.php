<?php

namespace MBLSolutions\AuditLogging\Services\Log;

use MBLSolutions\AuditLogging\Drivers\Log\LogDriver;
use MBLSolutions\AuditLogging\Support\DTO\RequestResponseDTO;

class AuditLoggingService
{
    protected RequestResponseDTO $dto;

    public function __construct(RequestResponseDTO $dto)
    {
        $this->dto = $dto;
    }

    public function createLog(): void
    {
        $this->getLogDriver()->store();
    }

    public function getLogDriver(): LogDriver
    {
        /** @var LogDriver $namespace */
        $namespace = config('audit-logging.drivers.' . config('audit-logging.logger') . '.driver');

        return new $namespace($this->dto);
    }

}