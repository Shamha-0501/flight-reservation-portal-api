<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantDomain extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_domains';
    protected $fillable = [
        'tenant_id',
        'host',
        'type',
        'is_primary',
        'dns_token',
        'verified_at',
        'ssl_status',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
