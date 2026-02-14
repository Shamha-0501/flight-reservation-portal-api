<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantAssets extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_assets';
    protected $fillable = [
        'tenant_id',
        'kind',
        'disk',
        'path',
        'mime',
        'size',
        'label'
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
