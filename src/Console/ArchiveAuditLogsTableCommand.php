<?php

namespace MBLSolutions\AuditLogging\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ArchiveAuditLogsTableCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'audit:database:archive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive data in existing table and create new fresh one';

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
        $newTable = $table."_new";
        $archivedTable = $table."_archive_".time();

        //create a new table based on existing logs
        DB::connection(config('audit-logging.drivers.database.connection'))
            ->statement("CREATE TABLE $newTable LIKE $table");

        //rename existing table to archive
        DB::connection(config('audit-logging.drivers.database.connection'))
            ->statement("ALTER TABLE $table RENAME $archivedTable");

        //rename new table to default log table name
        DB::connection(config('audit-logging.drivers.database.connection'))
            ->statement("ALTER TABLE $newTable RENAME $table");

        $this->info('Archiving successful!');

    }


}
