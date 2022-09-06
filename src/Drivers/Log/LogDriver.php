<?php

namespace MBLSolutions\AuditLogging\Drivers\Log;

use MBLSolutions\AuditLogging\Support\DTO\RequestResponseDTO;

interface LogDriver
{

    public function __construct(RequestResponseDTO $dto);

    /**
     * Store the log using the specified driver
     *
     * @return bool
     */
    public function store(): bool;

}