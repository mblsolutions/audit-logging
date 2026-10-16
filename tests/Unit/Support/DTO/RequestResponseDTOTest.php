<?php

namespace MBLSolutions\AuditLogging\Tests\Unit\Support\DTO;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use MBLSolutions\AuditLogging\Support\DTO\RequestResponseDTO;
use MBLSolutions\AuditLogging\Support\Enums\LogType;
use MBLSolutions\AuditLogging\Tests\LaravelTestCase;
use PHPUnit\Framework\Attributes\Test;

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

    #[Test]
    public function it_masks_basic_and_digest_auth_copies_of_the_authorization_header(): void
    {
        config(['audit-logging.loggable.request_header' => true]);

        $basic = Request::create('/api/test', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('alice:s3cret-pass')]);
        $digest = Request::create('/api/test', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Digest username="alice", response="6629fae49393a053"']);

        $basicHeaders = json_decode((new RequestResponseDTO(LogType::API, $basic, new Response('{}', 200)))->requestHeaders, true);
        $digestHeaders = json_decode((new RequestResponseDTO(LogType::API, $digest, new Response('{}', 200)))->requestHeaders, true);

        $this->assertSame('****', $basicHeaders['authorization']);
        $this->assertSame('****', $basicHeaders['php-auth-pw']);
        $this->assertSame(['alice'], $basicHeaders['php-auth-user']);
        $this->assertSame('****', $digestHeaders['authorization']);
        $this->assertSame('****', $digestHeaders['php-auth-digest']);
    }

    #[Test]
    public function it_masks_protected_keys_in_the_uri(): void
    {
        config(['audit-logging.protected_keys' => ['token', 'pan', 'pin']]);

        $request = Request::create('/api/reset-password/secret-token?pan=6331101999990016&pin=1234&page=2', 'GET');
        $route = (new Route('GET', 'api/reset-password/{token}', fn () => null))->bind($request);
        $request->setRouteResolver(fn () => $route);

        $dto = new RequestResponseDTO(LogType::API, $request, new Response('{}', 200));

        $this->assertSame('/api/reset-password/************?pan=****************&pin=****&page=2', $dto->uri);
    }

    #[Test]
    public function it_keeps_the_uri_when_nothing_is_protected(): void
    {
        $request = Request::create('/api/orders/123?page=2', 'GET');
        $route = (new Route('GET', 'api/orders/{order}', fn () => null))->bind($request);
        $request->setRouteResolver(fn () => $route);

        $dto = new RequestResponseDTO(LogType::API, $request, new Response('{}', 200));

        $this->assertSame('/api/orders/123?page=2', $dto->uri);
    }
}
