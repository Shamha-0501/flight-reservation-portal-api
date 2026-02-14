<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantFeature extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_features';
    protected $fillable = [
        'tenant_id',
        'feature_key',
        'enabled',
        'config',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
