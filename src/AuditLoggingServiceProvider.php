<?php

namespace MBLSolutions\AuditLogging;

use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use MBLSolutions\AuditLogging\Console\AddIndexToAuditLogsCommand;
use MBLSolutions\AuditLogging\Console\ArchiveAuditLogsTableCommand;
use MBLSolutions\AuditLogging\Console\SystemAuditLogsTableCommand;
use MBLSolutions\AuditLogging\Http\Middleware\ApiAuditLogging;
use MBLSolutions\AuditLogging\Http\Middleware\ConsoleAuditLogging;
use MBLSolutions\AuditLogging\Http\Middleware\WebAuditLogging;

class AuditLoggingServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->mergeDefaultConfig();
    }

    /**
     * Add keys and drivers that older published configs don't have. App values always win.
     * Laravel's mergeConfigFrom() isn't enough: it would drop the new drivers, because apps already have "drivers".
     */
    protected function mergeDefaultConfig(): void
    {
        if ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached()) {
            return;
        }

        $config = $this->app->make('config');
        $defaults = require __DIR__ . '/../config/audit-logging.php';
        $published = (array) $config->get('audit-logging', []);

        $merged = array_merge($defaults, $published);
        $merged['drivers'] = (array) ($published['drivers'] ?? []) + $defaults['drivers'];

        $config->set('audit-logging', $merged);
    }

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
                AddIndexToAuditLogsCommand::class,
                ArchiveAuditLogsTableCommand::class,
            ]);
        }
    }

}