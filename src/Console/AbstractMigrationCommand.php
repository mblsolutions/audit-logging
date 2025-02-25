<?php

namespace MBLSolutions\AuditLogging\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;

abstract class AbstractMigrationCommand extends Command
{
    /**
     * The filesystem instance.
     *
     * @var Filesystem
     */
    protected $files;

    /**
     * @var Composer
     */
    protected $composer;

    /**
     * Name of the stub inside the stubs/ directory for the migration
     *
     * @var string
     */
    protected string $stub;

    /**
     * Create a new queue job table command instance.
     *
     * @param Filesystem $files
     * @param Composer $composer
     * @return void
     */
    public function __construct(Filesystem $files, Composer $composer)
    {
        parent::__construct();

        if (! str_contains($this->name, '--force')) {
            $this->name .= ' {{ --force }}';
        }

        $this->files = $files;
        $this->composer = $composer;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $table = $this->laravel['config']['audit-logging.drivers.database.table'];

        $this->replaceMigration(
            $this->createBaseMigration($table), $table
        );

        $this->info('Migration created successfully!');

        $this->composer->dumpAutoloads();
    }

    /**
     * Create a base migration file for the table.
     *
     * @param  string  $table
     * @return string
     */
    protected function createBaseMigration($table = 'system_audit_logs')
    {
        return $this->laravel['migration.creator']->create(
            $this->migrationName(),
            $this->laravel->databasePath().'/migrations'
        );
    }

    /**
     * Returns the name of the migration that will be created in database/migrations
     *
     * e.g. a stub file named add_index_system_audit_logs.stub will return add_index_system_audit_logs
     *
     * @return string
     */
    protected function migrationName(): string
    {
        return basename($this->stub);
    }

    /**
     * Replace the generated migration with the job table stub.
     *
     * @param  string  $path
     * @param  string  $table
     * @param  string  $tableClassName
     * @return void
     */
    protected function replaceMigration($path, $table)
    {
        $stub = str_replace(
            ['{{table}}', '{{migrationClassName}}'],
            [$table, $this->migrationClassName()],
            $this->files->get($this->stubFilePath())
        );

        $exists = $this->files->exists($path);
        $force = $this->option('force');

        if ($exists && !$force) {
            $this->error('Migration already exists! Use --force to overwrite');
            return;
        } else {
            $this->warn('Migration already exists! Overwriting');
        }

        $this->files->put($path, $stub);
    }

    public function migrationClassName(): string
    {
        return Str::camel($this->migrationName());
    }

    private function stubFilePath(): string
    {
        return __DIR__ . '/stubs/add_index_system_audit_logs.stub';
    }
}
