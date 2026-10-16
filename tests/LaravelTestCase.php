<?php

namespace MBLSolutions\AuditLogging\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as OTBTestCase;

class LaravelTestCase extends OTBTestCase
{
    use RefreshDatabase;

    protected function getEnvironmentSetUp($app): void
    {
        // Use the package defaults, like a freshly published config.
        $app['config']->set('audit-logging', require __DIR__ . '/../config/audit-logging.php');
    }
}
