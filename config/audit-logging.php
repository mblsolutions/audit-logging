<?php

return [

    /*
     |--------------------------------------------------------------------------
     | Audit Logging Settings
     |--------------------------------------------------------------------------
     |
     | Audit Logging is disabled by default, you can enable audit logging by
     | setting enabled to true.
     |
     */

    'enabled' => env('AUDIT_LOGGING_ENABLED', false),

    /*
     |--------------------------------------------------------------------------
     | Event Audit Logging
     |--------------------------------------------------------------------------
     |
     | Event Audit Logging is enabled by default, you can disabled event audit
     | by setting event_enabled to false.
     |
     */

    'event_enabled' => env('EVENT_AUDIT_LOGGING_ENABLED', true),

    /*
     |--------------------------------------------------------------------------
     | Audit Logger
     |--------------------------------------------------------------------------
     |
     | Here you can configure which audit logging driver should be used to log
     | auditing information.
     |
     */

    'logger' => env('AUDIT_LOGGING_LOGGER', 'database'),

    /*
     |--------------------------------------------------------------------------
     | Loggable Elements
     |--------------------------------------------------------------------------
     |
     | Depending on the selected driver, configure what elements are logged as
     | part of the request/response header and content data.
     |
     */

    'loggable' => [
        'request_header' => false,
        'request_body' => true,
        'response_header' => false,
        'response_body' => true,
    ],

    /*
     |--------------------------------------------------------------------------
     | Protected Keys
     |--------------------------------------------------------------------------
     |
     | To protect sensitive data being logged in the database in plain text
     | you can add keys to this array that should be masked with an astrix.
     |
     */

    'protected_keys' => [
        'password',
        'password_confirmation',
    ],

    /*
     |--------------------------------------------------------------------------
     | Max Loggable Length
     |--------------------------------------------------------------------------
     |
     | Configure the maximum amount of data (in bytes) that can be logged as part of the
     | request/response header and body elements.
     |
     */

    'max_loggable_length' => env('AUDIT_LOGGING_MAX_LENGTH', 10024),


    /*
     |--------------------------------------------------------------------------
     | Audit Logging Driver
     |--------------------------------------------------------------------------
     |
     | Audit Logging is disabled by default, you can enable audit logging by
     | setting enabled to true.
     |
     | Drivers: "storage", "database"
     |
     | Please Note: The storage driver should only be used for only be used for
     | local development.
     |
     */

    'drivers' => [

        'storage' => [
            'driver' => \MBLSolutions\AuditLogging\Drivers\Log\Storage::class,
            'path' => storage_path(env('AUDIT_LOGGING_STORAGE_PATH', 'logs/audit')),
        ],

        'database' => [
            'driver' => \MBLSolutions\AuditLogging\Drivers\Log\Database::class,
            'connection' => env('AUDIT_LOGGING_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),
            'table' => env('AUDIT_LOGGING_TABLE', 'system_audit_logs'),
        ],

    ],

    /*
     |--------------------------------------------------------------------------
     | Authentication Guard
     |--------------------------------------------------------------------------
     |
     | The authentication guard being used by the system e.g. sanctum
     |
     */

    'auth_guard' => null,


];