<?php

namespace MBLSolutions\AuditLogging\Drivers\Log;

interface LogDriver
{

    /**
     * Store the log using the specified driver
     *
     * @return bool
     */
    public function store(): bool;

}