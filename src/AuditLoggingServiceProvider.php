<?php

namespace MBLSolutions\AuditLogging;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use MBLSolutions\AuditLogging\Console\SystemAuditLogsTableCommand;
use MBLSolutions\AuditLogging\Http\Middleware\ApiAuditLogging;
use MBLSolutions\AuditLogging\Http\Middleware\ConsoleAuditLogging;
use MBLSolutions\AuditLogging\Http\Middleware\WebAuditLogging;

class AuditLoggingServiceProvider extends ServiceProvider
{

    public function boot(): void
    {
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('web-audit-logging', WebAuditLogging::class);
        $router->aliasMiddleware('api-audit-logging', ApiAuditLogging::class);
        $router->aliasMiddleware('console-audit-logging', ConsoleAuditLogging::class);

        // Publish the package config
        $this->publishes([
            __DIR__ . '/../config/audit-logging.php' => config_path('audit-logging.php'),
        ], 'audit-logging-config');

        // Register the command if we are using the application via the CLI
        if ($this->app->runningInConsole()) {
            $this->commands([
                SystemAuditLogsTableCommand::class,
            ]);
        }
    }

}