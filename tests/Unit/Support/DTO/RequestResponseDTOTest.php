<?php

namespace MBLSolutions\AuditLogging\Tests\Unit\Support\DTO;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use MBLSolutions\AuditLogging\Support\DTO\RequestResponseDTO;
use MBLSolutions\AuditLogging\Support\Enums\LogType;
use MBLSolutions\AuditLogging\Tests\LaravelTestCase;

class RequestResponseDTOTest extends LaravelTestCase
{
    /** @test **/
    public function it_creates_a_request_response_dto_and_provides_string_id_and_expected_fields(): void
    {
        $request = Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $response = new Response('{"message":"ok"}', 200, ['Content-Type' => 'application/json']);

        $dto = new RequestResponseDTO(LogType::WEB, $request, $response);

        // id should be a string (UUID) and not empty
        $this->assertTrue(is_string($dto->id));
        $this->assertNotEmpty($dto->id);

        // basic request/response fields
        $this->assertSame('GET', $dto->method);
        $this->assertSame('/test', $dto->uri);
        $this->assertSame(200, $dto->status);

        // dateTime should be present
        $this->assertTrue(is_string($dto->dateTime));
        $this->assertNotEmpty($dto->dateTime);
    }

    /** @test */
    public function it_creates_a_request_response_dto_and_provides_request_and_response_headers(): void
    {
        // Ensure headers and bodies are logged for the DTO
        config([
            'audit-logging.loggable.request_header' => true,
            'audit-logging.loggable.response_header' => true,
            'audit-logging.loggable.request_body' => true,
            'audit-logging.loggable.response_body' => true,
        ]);

        // Provide a custom request header via the server array (HTTP_ prefix)
        $server = ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_TEST_HEADER' => 'TestValue'];
        $request = Request::create('/test', 'GET', [], [], [], $server);

        // Create a response with a custom header
        $response = new Response('{"message":"ok"}', 200, ['X-Response-Test' => 'RespValue']);

        $dto = new RequestResponseDTO(LogType::WEB, $request, $response);

        // request headers should be JSON and contain our custom header
        $this->assertNotNull($dto->requestHeaders, 'requestHeaders should not be null when request_header logging is enabled');
        $requestHeaders = json_decode($dto->requestHeaders, true);
        $this->assertIsArray($requestHeaders);
        $this->assertArrayHasKey('x-test-header', $requestHeaders);
        $this->assertSame('TestValue', $requestHeaders['x-test-header'][0]);

        // response headers should be JSON and contain our custom response header
        $this->assertNotNull($dto->responseHeader, 'responseHeader should not be null when response_header logging is enabled');
        $responseHeaders = json_decode($dto->responseHeader, true);
        $this->assertIsArray($responseHeaders);
        $this->assertArrayHasKey('x-response-test', $responseHeaders);
        $this->assertSame('RespValue', $responseHeaders['x-response-test'][0]);
    }
}
