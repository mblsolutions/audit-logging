<?php

namespace MBLSolutions\AuditLogging\Services\Log;

use MBLSolutions\AuditLogging\Drivers\Log\LogDriver;
use MBLSolutions\AuditLogging\Support\DTO\RequestResponseDTO;
use RuntimeException;

class AuditLoggingService
{
    protected RequestResponseDTO $dto;

    protected ?string $driver;

    public function __construct(RequestResponseDTO $dto, ?string $driver = null)
    {
        $this->dto = $dto;
        $this->driver = $driver;
    }

    public function createLog(): void
    {
        foreach (static::driverNames($this->driver) as $driver) {
            $this->getLogDriver($driver)->store();
        }
    }

    public function getLogDriver(?string $driver = null): LogDriver
    {
        $driver ??= $this->driver ?? config('audit-logging.logger');

        /** @var LogDriver $namespace */
        $namespace = config('audit-logging.drivers.' . $driver . '.driver');

        if (! $namespace) {
            throw new RuntimeException("Audit logging driver [{$driver}] is missing from config/audit-logging.php.");
        }

        return new $namespace($this->dto);
    }

    /**
     * The "stack" logger writes to several drivers, e.g. database and cloudwatch while moving over.
     *
     * @return array<int, string>
     */
    public static function driverNames(?string $driver = null): array
    {
        $driver ??= config('audit-logging.logger');

        if ($driver === 'stack') {
            // Older published configs have no stack block: write to both rather than to nothing.
            $drivers = (array) config('audit-logging.drivers.stack.drivers', ['database', 'cloudwatch']);

            // Trim, so "database, cloudwatch" works.
            return array_values(array_filter(array_map(fn ($name) => trim((string) $name), $drivers)));
        }

        return [$driver];
    }

}
