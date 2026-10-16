<?php

namespace MBLSolutions\AuditLogging\Tests\Unit\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use MBLSolutions\AuditLogging\Contracts\ResolvesAuditContext;
use MBLSolutions\AuditLogging\Support\AuditEvent;
use MBLSolutions\AuditLogging\Support\DTO\RequestResponseDTO;
use MBLSolutions\AuditLogging\Support\Enums\LogType;
use MBLSolutions\AuditLogging\Tests\LaravelTestCase;
use PHPUnit\Framework\Attributes\Test;

class AuditEventTest extends LaravelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.env' => 'production',
            'audit-logging.system' => 'test-system',
            'audit-logging.protected_keys' => ['password', 'pin'],
        ]);
    }

    #[Test]
    public function it_builds_a_flat_event_with_real_json_bodies(): void
    {
        $request = $this->jsonRequest('POST', '/api/orders/15', ['name' => 'Test', 'password' => 'secret', 'PIN' => '1234']);
        $request->headers->set('User-Agent', 'phpunit');
        $request->headers->set('X-Request-Id', 'req-123');

        $route = (new Route('POST', 'api/orders/{order}', []))->name('orders.update')->bind($request);
        $request->setRouteResolver(fn () => $route);

        $response = new Response('{"id":15,"status":"done"}', 201, ['Content-Type' => 'application/json']);

        $event = AuditEvent::fromDto(new RequestResponseDTO(LogType::API, $request, $response));

        $this->assertSame(1, $event['schema_version']);
        $this->assertSame('test-system', $event['system']);
        $this->assertSame('production', $event['env']);
        $this->assertSame('api', $event['type']);
        $this->assertSame('POST', $event['method']);
        $this->assertSame('/api/orders/15', $event['uri']);
        $this->assertSame('orders.update', $event['route']);
        $this->assertSame(201, $event['status']);
        $this->assertSame('phpunit', $event['user_agent']);
        $this->assertSame('req-123', $event['request_id']);
        $this->assertSame(['order' => '15'], $event['reference']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $event['occurred_at']);

        // Bodies are objects, not JSON strings.
        $this->assertSame('Test', $event['request_body']['name']);
        $this->assertSame('******', $event['request_body']['password']);
        $this->assertSame('****', $event['request_body']['PIN']);
        $this->assertSame(['id' => 15, 'status' => 'done'], $event['response_body']);
        $this->assertArrayNotHasKey('request_body_truncated', $event);

        // Search fields come before the bodies.
        $keys = array_keys($event);
        $this->assertLessThan(array_search('request_body', $keys, true), array_search('user_id', $keys, true));
    }

    #[Test]
    public function auth_and_cookie_headers_are_always_masked(): void
    {
        // simfoni-retail logs request headers and its protected_keys has no authorization or cookie.
        config([
            'audit-logging.loggable.request_header' => true,
            'audit-logging.loggable.response_header' => true,
            'audit-logging.protected_keys' => ['password'],
        ]);

        $request = Request::create('/api/orders', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer secret-token',
            'HTTP_COOKIE' => 'session=abc',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = new Response('{}', 200, ['Set-Cookie' => 'session=xyz']);

        $event = AuditEvent::fromDto(new RequestResponseDTO(LogType::API, $request, $response));

        $this->assertSame('****', $event['request_headers']['authorization']);
        $this->assertSame('****', $event['request_headers']['cookie']);
        $this->assertSame(['application/json'], $event['request_headers']['accept']);
        $this->assertSame('****', $event['response_headers']['set-cookie']);
        $this->assertStringNotContainsString('secret-token', json_encode($event));
    }

    #[Test]
    public function it_takes_the_trace_id_from_the_shared_log_context(): void
    {
        Log::shareContext(['traceid' => '4bf92f3577b34da6a3ce929d0e0e4736']);

        $event = AuditEvent::fromDto(new RequestResponseDTO(LogType::API, Request::create('/api/orders'), new Response('')));

        $this->assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $event['trace_id']);
    }

    #[Test]
    public function it_flags_a_body_that_was_cut(): void
    {
        config(['audit-logging.max_loggable_length' => 20]);

        $request = $this->jsonRequest('POST', '/api/orders', ['description' => str_repeat('x', 100)]);

        $event = AuditEvent::fromDto(new RequestResponseDTO(LogType::API, $request, new Response('')));

        $this->assertIsString($event['request_body']);
        $this->assertTrue($event['request_body_truncated']);
    }

    #[Test]
    public function it_adds_app_context_without_overwriting_core_fields(): void
    {
        config(['audit-logging.context_resolver' => TestContextResolver::class]);

        $event = AuditEvent::fromDto(new RequestResponseDTO(LogType::API, Request::create('/api/orders'), new Response('')));

        $this->assertSame(345, $event['order_id']);
        $this->assertSame(12, $event['account_id']);
        $this->assertSame(200, $event['status']);
    }

    #[Test]
    public function a_broken_context_resolver_does_not_break_the_request(): void
    {
        config(['audit-logging.context_resolver' => BrokenContextResolver::class]);

        $dto = new RequestResponseDTO(LogType::API, Request::create('/api/orders'), new Response(''));

        $this->assertSame([], $dto->context);
    }

    protected function jsonRequest(string $method, string $uri, array $body): Request
    {
        return Request::create($uri, $method, [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($body));
    }
}

class TestContextResolver implements ResolvesAuditContext
{
    public function resolve(Request $request, $response, $user = null): array
    {
        return ['order_id' => 345, 'account_id' => 12, 'status' => 'overwritten'];
    }
}

class BrokenContextResolver implements ResolvesAuditContext
{
    public function resolve(Request $request, $response, $user = null): array
    {
        throw new \RuntimeException('boom');
    }
}
