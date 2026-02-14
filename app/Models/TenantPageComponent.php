<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantPageComponent extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_page_components';
    protected $fillable = [
        'tenant_id',
        'page_id',
        'component_type',
        'variant',
        'sort_order',
        'is_enabled',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function page()
    {
        return $this->belongsTo(TenantPages::class, 'page_id');
    }
}
