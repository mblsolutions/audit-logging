<?php

namespace MBLSolutions\AuditLogging\Drivers\Log;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use MBLSolutions\AuditLogging\Support\DTO\RequestResponseDTO;

class Database implements LogDriver
{
    public RequestResponseDTO $dto;

    private array $config;

    public function __construct(RequestResponseDTO $dto)
    {
        $this->dto = $dto;
        $this->config = config('audit-logging.drivers.database');
    }

    public function store(): bool
    {
        $data = $this->mapAuditData();

        $this->getBuilder()->insert($data);

        return true;
    }

    protected function mapAuditData(): array
    {
        $mapAuditData = [
            'id' => $this->dto->id,
            'reference' => $this->dto->reference,
            'method' => $this->dto->method,
            'uri' => $this->dto->uri,
            'status' => $this->dto->status,
            'type' => $this->dto->type,
            'auth' => $this->dto->auth,
            'request_headers' => $this->dto->requestHeaders,
            'request_body' => $this->dto->requestBody,
            'request_fingerprint' => $this->dto->requestFingerprint,
            'response_headers' => $this->dto->responseHeader,
            'response_body' => $this->dto->responseBody,
            'response_fingerprint' => $this->dto->responseFingerprint,
            'remote_address' => $this->dto->remoteAddress,
            'created_at' => $this->dto->dateTime,
            'updated_at' => $this->dto->dateTime,            
        ];
        
        // Provide compatibility on data manipulation to import new version of audit log package
        $extraColumn = config('audit-logging.extra_column');

        if (!is_null($extraColumn)){
            foreach ($extraColumn as $auditKey => $dtoProperty) {                
                if (isset($this->dto->$dtoProperty)) {
                    $mapAuditData[$auditKey] = $this->dto->$dtoProperty;
                }
            }
        }
        return $mapAuditData;
    }

    protected function getBuilder(): Builder
    {
        return DB::connection($this->config['connection'])->table($this->config['table']);
    }

}