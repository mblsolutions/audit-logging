<?php

namespace MBLSolutions\AuditLogging\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;

class AddIndexToAuditLogsCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'audit:update:addindex';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add an index for created_at on existing database table';

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
     * Create a new queue job table command instance.
     *
     * @param Filesystem $files
     * @param Composer $composer
     * @return void
     */
    public function __construct(Filesystem $files, Composer $composer)
    {
        parent::__construct();

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
            $this->createBaseMigration($table), $table, Str::studly($table)
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
            'update_'.$table.'_table_add_index', $this->laravel->databasePath().'/migrations'
        );
    }

    /**
     * Replace the generated migration with the job table stub.
     *
     * @param  string  $path
     * @param  string  $table
     * @param  string  $tableClassName
     * @return void
     */
    protected function replaceMigration($path, $table, $tableClassName)
    {
        $stub = str_replace(
            ['{{table}}', '{{tableClassName}}'],
            [$table, $tableClassName],
            $this->files->get(__DIR__.'/stubs/system_audit_logs.stub')
        );

        $this->files->put($path, $stub);
    }
}
