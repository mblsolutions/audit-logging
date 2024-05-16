<?php

namespace MBLSolutions\AuditLogging\Models;

use MBLSolutions\AuditLogging\Traits\BindsDynamically;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lerouse\LaravelRepository\HasRepository;

class AuditLog extends Model
{
    use SoftDeletes, HasRepository, BindsDynamically;

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'reference',
        'method',
        'uri',
        'status',
        'type',
        'auth',
        'request_headers',
        'request_body',
        'request_fingerprint',
        'response_headers',
        'response_body',
        'response_fingerprint',
        'remote_address',
    ];
}