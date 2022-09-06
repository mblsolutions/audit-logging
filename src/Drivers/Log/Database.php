<?php

namespace MBLSolutions\AuditLogging\Drivers\Log;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use MBLSolutions\AuditLogging\Support\Concerns\ShouldMaskSensitiveData;
use MBLSolutions\AuditLogging\Support\Enums\LogType;

class Database implements LogDriver
{
    use ShouldMaskSensitiveData;

    protected Request $request;

    protected $response;

    private array $config;

    public function __construct(Request $request, $response)
    {
        $this->request = $request;
        $this->response = $response;

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
        return [
            'id' => Str::uuid(),
            'reference' => null, // TODO
            'method' => $this->request->getMethod(),
            'uri' => $this->request->getMethod(),
            'status' => $this->response->getStatusCode(),
            'type' => LogType::WEB, // TODO
            'auth' => $this->getAuthentication(),
            'request_headers' => config('audit-logging.loggable.request_header') ? $this->convertDataToJson($this->request->headers) : null,
            'request_body' => config('audit-logging.loggable.request_body') ? $this->convertDataToJson($this->request->getContent()) : null,
            'request_fingerprint' => $this->generateFingerprint($this->request->headers, $this->request->getContent()),
            'response_headers' => config('audit-logging.loggable.response_header') ? $this->convertDataToJson($this->response->headers) : null,
            'response_body' => config('audit-logging.loggable.response_body') ? $this->convertDataToJson($this->response->getContent()) : null,
            'response_fingerprint' => $this->generateFingerprint($this->response->headers, $this->response->getContent()),
            'remote_address' => $this->request->ip(),
            'created_at' => $now = Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
        ];
    }

    public function getAuthentication(): ?string
    {
        $auth = Auth::guard(config('audit-logging.auth_guard'))->user();

        try {
            if ($auth) {
                return json_encode([
                    'id' => optional($auth)->getKey(),
                    'name' => optional($auth)->getAttribute('name'),
                    'email' => optional($auth)->getAttribute('email'),
                ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            }
        } catch (JsonException $e) {}

        return null;
    }

    protected function getBuilder(): Builder
    {
        return DB::connection($this->config['connection'])->table($this->config['table']);
    }

    private function convertDataToJson($data = null): ?string
    {
        try {
            if ($data !== null) {
                $sanitised = $this->maskSensitiveData($data, config('audit-logging.protected_keys'));

                if (is_string($sanitised)) {
                    $sanitised = Str::limit($sanitised, config('audit-logging.max_loggable_length'));
                }

                return json_encode($sanitised, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            }
        } catch (JsonException $e) {}

        return null;
    }

    private function generateFingerprint($a = null, $b = null): ?string
    {
         if ($a !== null || $b !== null) {
             return md5($a . $b);
         }

         return null;
    }

}