<?php

namespace MBLSolutions\AuditLogging\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use MBLSolutions\AuditLogging\Services\Log\AuditLoggingService;
use MBLSolutions\AuditLogging\Support\DTO\RequestResponseDTO;
use Throwable;

class GenerateAuditLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // About 20 minutes of retries, so a short CloudWatch outage loses nothing.
    public int $tries = 5;

    public array $backoff = [10, 60, 300, 900];

    public RequestResponseDTO $dto;

    public ?string $driver = null;

    public function __construct(RequestResponseDTO $dto, ?string $driver = null)
    {
        $this->dto = $dto;
        $this->driver = $driver;
    }

    public function handle(): void
    {
        (new AuditLoggingService($this->dto, $this->driver))->createLog();
    }

    public function failed(?Throwable $exception = null): void
    {
        // No bodies here: this goes to the normal app log. No message either, a DB error holds the insert SQL.
        Log::error('Audit log could not be stored.', [
            'audit_event_id' => $this->dto->id,
            'driver' => $this->driver,
            'type' => $this->dto->type,
            'method' => $this->dto->method,
            'route' => $this->dto->route,
            'status' => $this->dto->status,
            'user_id' => $this->dto->userId,
            'error' => $exception ? get_class($exception) : null,
            'error_code' => $exception?->getCode(),
        ]);
    }

}
