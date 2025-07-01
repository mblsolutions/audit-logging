<?php

namespace MBLSolutions\AuditLogging\Support\DTO;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use JsonException;
use MBLSolutions\AuditLogging\Support\Concerns\ShouldMaskSensitiveData;

class RequestResponseDTO
{
    use ShouldMaskSensitiveData;

    public string $id;
    public ?string $reference = null;
    public ?string $method = null;
    public ?string $uri = null;
    public ?int $status = null;
    public string $type;
    public ?string $remoteAddress;
    public ?string $auth = null;
    public ?string $requestHeaders = null;
    public ?string $requestBody = null;
    public ?string $requestFingerprint = null;
    public ?string $responseHeader = null;
    public ?string $responseBody = null;
    public ?string $responseFingerprint = null;
    public string $dateTime;
    public ?int $userId = null;

    public function __construct(string $type, Request $request, $response, $auth = null)
    {
        $this->id = Str::uuid();
        $this->reference = $this->handleRouteParameters($request);
        $this->method = $request->getMethod();
        $this->uri = $request->getRequestUri();
        $this->status = $response->getStatusCode();
        $this->type = $type;
        $this->remoteAddress = $request->ip();
        $this->auth = $this->handleAuthData($auth);
        $this->requestHeaders = config('audit-logging.loggable.request_header') ? $this->convertDataToJson($request->headers) : null;
        $this->requestBody = config('audit-logging.loggable.request_body') ? $this->handleBodyIfJson($request) : null;
        $this->requestFingerprint = $this->generateFingerprint($request->headers, $request->getContent());
        $this->responseHeader = config('audit-logging.loggable.response_header') ? $this->convertDataToJson($response->headers) : null;
        $this->responseBody = config('audit-logging.loggable.response_body') ? $this->handleBodyIfJson($response) : null;
        $this->responseFingerprint = $this->generateFingerprint($response->headers, $response->getContent());
        $this->dateTime = Carbon::now()->toDateTimeString();
        $this->userId = optional($auth)->getKey();
    }

    public function handleBodyIfJson($data): ?string
    {
        $content = $data->getContent();

        if (config('audit-logging.json_body_only') !== true) {
            return $this->convertDataToJson($content);
        }

        if ($this->isJson($content)) {
            return $this->convertDataToJson($content);
        }

        return null;
    }

    public function convertDataToJson($data = null): ?string
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

    public function generateFingerprint($a = null, $b = null): ?string
    {
        if ($a !== null || $b !== null) {
            return md5($a . $b);
        }

        return null;
    }

    public function handleRouteParameters(Request $request): ?string
    {
        $route = $request->route();

        if ($route) {
            return $this->convertDataToJson($route->originalParameters());
        }

        return null;
    }

    public function handleAuthData($auth = null): ?string
    {
        if ($auth) {
            return $this->convertDataToJson([
                'id' => optional($auth)->getKey(),
                'name' => optional($auth)->getAttribute('name'),
                'email' => optional($auth)->getAttribute('email'),
            ]);
        }

        return null;
    }

}