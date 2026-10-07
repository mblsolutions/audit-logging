<?php

namespace MBLSolutions\AuditLogging\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use MBLSolutions\AuditLogging\AuditLoggingServiceProvider;
use MBLSolutions\AuditLogging\Drivers\Log\CloudWatch;
use MBLSolutions\AuditLogging\Drivers\Log\Database;
use MBLSolutions\AuditLogging\Services\Log\AuditLoggingService;
use MBLSolutions\AuditLogging\Support\DTO\RequestResponseDTO;
use MBLSolutions\AuditLogging\Support\Enums\LogType;
use MBLSolutions\AuditLogging\Tests\LaravelTestCase;
use PHPUnit\Framework\Attributes\Test;

class AuditLoggingServiceProviderTest extends LaravelTestCase
{
    #[Test]
    public function it_adds_the_new_keys_and_drivers_to_an_old_published_config(): void
    {
        // Like the configs the apps published from v2.0.x: no system, queue, cloudwatch or stack.
        config()->set('audit-logging', [
            'enabled' => true,
            'logger' => 'cloudwatch',
            'protected_keys' => ['password', 'token'],
            'drivers' => [
                'database' => [
                    'driver' => Database::class,
                    'connection' => 'audit',
                    'table' => 'app_audit_logs',
                ],
            ],
        ]);

        (new AuditLoggingServiceProvider($this->app))->register();

        // App values are kept.
        $this->assertTrue(config('audit-logging.enabled'));
        $this->assertSame(['password', 'token'], config('audit-logging.protected_keys'));
        $this->assertSame('app_audit_logs', config('audit-logging.drivers.database.table'));

        // Missing keys come from the package.
        $this->assertNotEmpty(config('audit-logging.system'));
        $this->assertArrayHasKey('queue', config('audit-logging'));
        $this->assertSame(CloudWatch::class, config('audit-logging.drivers.cloudwatch.driver'));
        $this->assertSame(['database', 'cloudwatch'], config('audit-logging.drivers.stack.drivers'));

        // So AUDIT_LOGGING_LOGGER=cloudwatch works without editing the app config.
        $dto = new RequestResponseDTO(LogType::API, Request::create('/api/balance', 'GET'), new Response('{}', 200));

        $this->assertInstanceOf(CloudWatch::class, (new AuditLoggingService($dto))->getLogDriver());
    }

    #[Test]
    public function it_uses_the_package_config_when_none_is_published(): void
    {
        config()->set('audit-logging', null);

        (new AuditLoggingServiceProvider($this->app))->register();

        $this->assertSame(require __DIR__ . '/../../config/audit-logging.php', config('audit-logging'));
    }
}
