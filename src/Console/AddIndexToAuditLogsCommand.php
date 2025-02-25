<?php

namespace MBLSolutions\AuditLogging\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;

class AddIndexToAuditLogsCommand extends AbstractMigrationCommand
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
     * Name of the stub inside the stubs/ directory for the migration
     *
     * @var string
     */
    protected string $stub = 'add_index_system_audit_logs.stub';

    /**
     * Create a new queue job table command instance.
     *
     * @param Filesystem $files
     * @param Composer $composer
     * @return void
     */
    public function __construct(Filesystem $files, Composer $composer)
    {
        parent::__construct($files, $composer);
    }
}
