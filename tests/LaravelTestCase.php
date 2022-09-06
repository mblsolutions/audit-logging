<?php

namespace MBLSolutions\AuditLogging\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as OTBTestCase;

class LaravelTestCase extends OTBTestCase
{
    use RefreshDatabase;
}