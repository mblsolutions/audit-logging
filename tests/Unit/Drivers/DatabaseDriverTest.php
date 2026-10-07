<?php

namespace MBLSolutions\AuditLogging\Tests\Unit\Drivers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MBLSolutions\AuditLogging\Jobs\GenerateAuditLogJob;
use MBLSolutions\AuditLogging\Support\DTO\RequestResponseDTO;
use MBLSolutions\AuditLogging\Support\Enums\LogType;
use MBLSolutions\AuditLogging\Tests\LaravelTestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class DatabaseDriverTest extends LaravelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'audit-logging.drivers.database.connection' => 'testing',
            'audit-logging.drivers.database.table' => 'system_audit_logs',
        ]);

        // Same columns as the audit:database:table stub.
        Schema::create('system_audit_logs', function (Blueprint $table) {
            $table->uuid('id');
            $table->json('reference')->nullable();
            $table->string('method', 7)->nullable();
            $table->text('uri')->nullable();
            $table->smallInteger('status')->nullable();
            $table->string('type', 16);
            $table->json('auth')->nullable();
            $table->json('request_headers')->nullable();
            $table->json('request_body')->nullable();
            $table->string('request_fingerprint', 32)->nullable();
            $table->json('response_headers')->nullable();
            $table->json('response_body')->nullable();
            $table->string('response_fingerprint', 32)->nullable();
            $table->string('remote_address', 39)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    #[Test]
    public function the_database_driver_still_writes_the_same_row(): void
    {
        $request = Request::create('/api/orders', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '10.0.0.1'], '{"serial":"123","password":"secret"}');
        $dto = new RequestResponseDTO(LogType::API, $request, new Response('{"ok":true}', 201));

        (new GenerateAuditLogJob($dto, 'database'))->handle();

        $row = DB::table('system_audit_logs')->first();

        $this->assertSame($dto->id, $row->id);
        $this->assertSame('POST', $row->method);
        $this->assertSame('/api/orders', $row->uri);
        $this->assertSame(201, (int) $row->status);
        $this->assertSame('10.0.0.1', $row->remote_address);
        // Bodies stay JSON text inside a JSON string, as before.
        $this->assertSame('{"serial":"123","password":"******"}', json_decode($row->request_body));
        $this->assertSame('{"ok":true}', json_decode($row->response_body));
    }

    #[Test]
    public function a_failed_insert_keeps_the_bodies_out_of_the_error(): void
    {
        Schema::drop('system_audit_logs');

        $request = Request::create('/api/orders', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"email":"alice@example.com"}');
        $dto = new RequestResponseDTO(LogType::API, $request, new Response('{"ok":true}', 201));

        try {
            (new GenerateAuditLogJob($dto, 'database'))->handle();
            $this->fail('The insert should fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringStartsWith('Audit log insert failed', $exception->getMessage());
            $this->assertStringNotContainsString('alice@example.com', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }
}
