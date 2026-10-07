<?php

namespace MBLSolutions\AuditLogging\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MBLSolutions\AuditLogging\Services\Log\AuditLoggingService;
use MBLSolutions\AuditLogging\Support\DTO\RequestResponseDTO;

class GenerateAuditLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

}
