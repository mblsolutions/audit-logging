<?php

namespace MBLSolutions\AuditLogging\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MBLSolutions\AuditLogging\Services\Log\AuditLoggingService;

class CreateAuditLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Request $request;

    public $response;

    public function __construct(Request $request, $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    public function handle(): void
    {
        (new AuditLoggingService($this->request, $this->response))->createLog();
    }

}