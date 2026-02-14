<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantSetting extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_settings';
    protected $fillable = [
        'tenat_id',
        'settings'
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
