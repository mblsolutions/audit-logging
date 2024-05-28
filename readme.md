# Audit Logging

Audit Logging for Laravel projects

## Installation

The recommended method to install LaravelRepository is with composer

```bash
php composer require mblsolutions/audit-logging
```

### Laravel without auto-discovery

If you don't use auto-discovery, add the ServiceProvider to the providers array in config/app.php

```php
\MBLSolutions\AuditLogging\AuditLoggingServiceProvider::class,
```

### Package configuration

```php
Copy the package configuration to your local config directory.
```

```bash
php artisan vendor:publish --tag=audit-logging-config
```

### Database Driver

If you would like to use the Database driver to store your audit logs, you will first need to create and run the database
driver migration.

```bash
php artisan audit:database:table
```

This will create a new migration in `database/migrations`, after creating this migration run the database migrations to
create the new table.

````bash
php artisan migrate
````

## Usage

The configuration and setup can be adjusted in the audit logging config file located in `config/audit-logging.php`. We 
recommend reading through the config file before enabling audit logging to ensure you have the optimum setup. 

### Enable/Disable Logging

Before logs are stored you will need to enable audit logging by setting the config values to true.

```php
// Request Audit Logging
'enabled' => env('AUDIT_LOGGING_ENABLED', false),

// Event Audit Logging
'event_enabled' => env('EVENT_AUDIT_LOGGING_ENABLED', true)
```

This can also be set in your .env file by using the corresponding environment variable

```dotenv
AUDIT_LOGGING_ENABLED=true
EVENT_AUDIT_LOGGING_ENABLED=false
```

### Audit Logging Middleware

To enable audit logging for routes there are multiple ways this can be achieved, middleware aliases has been created

+ `web-audit-logging`
+ `api-audit-logging`
+ `console-audit-logging`

The only differences with these middleware are the log type

#### Single Route

You can enable audit logging for a single route by applying the middleware to the route.

```php
use Illuminate\Routing\Route;

Route::get('/user', function () {
    //
})->middleware('web-audit-logging');
```

Or by using the fully qualified class name

```php
use Illuminate\Routing\Route;
use MBLSolutions\AuditLogging\Http\Middleware\AuditLogging;

Route::get('/user', function () {
    //
})->middleware(AuditLogging::class);
```

#### Route Groups

You can apply the middleware to a group of routes.

```php
use Illuminate\Routing\Route;

Route::middleware(['web-audit-logging'])->group(function () {

    Route::get('/', function () {
        //
    });

    Route::get('/user', function () {
        //
    });

});
```

#### Excluding Routes

You can exclude specific routes in a group of middleware. 

```php
use Illuminate\Routing\Route;

Route::middleware(['api-audit-logging'])->group(function () {

    Route::get('/', function () {
        //
    })->withoutMiddleware(['api-audit-logging']);

    Route::get('/user', function () {
        //
    });

});
```

### Optional Model/Trait for Log Retrieval

We have included an AuditLog Model with a BindsDynamically trait which you can use if you'd like to use an eloquent model for retrieving and formatting data.

+ **MBLSolutions\AuditLogging\Models\AuditLog** - Implements BindsDynamically trait. Can be extended if needed.
+ **MBLSolutions\AuditLogging\Traids\BindsDynamically** - Can be used in custom model or automatically as above.

#### Using Model with BindsDynamically trait

In order to use the AuditLog model (or any model implementing the BindsDynamically trait) you need to instantiate it with a connection and table as in the example below:
```php

$config = config('audit-logging.drivers.database');

$auditLog = new MBLSolutions\AuditLogging\Models\AuditLog;
$auditLog->setConnection($config['connection']);
$auditLog->setTable($config['table']);
```
You can then use the `$auditLog` as you would any eloquent model.

#### Archiving Logs

If you would like to archive your logs and continue with a fresh table then you can use the archive command by running:

````bash
php artisan audit:database:archive
````

If you have installed fresh from version 1.6.0 you are good to go, otherwise if you have an existing installation from an earlier version you should run the update migration to add an index to the audit logs created_at column (if you have not already). You just need to publish the update with:  

````bash
php artisan audit:update:addindex
````

and then remember to run

````bash
php artisan migrate
````

This should be done as soon as possible after your archiving as the more data in the table the longer the migration will take.


## License

Audit Logging is free software distributed under the terms of the MIT license.