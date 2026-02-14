<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantComponentRepeaterItem extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_component_repeater_items';
    protected $fillable = [
        'tenant_id',
        'component_id',
        'repeater_key',
        'sort_order',
    ];

    public function tenant()
    {
        return $this->belonsTo(Tenant::class, 'tenant_id');
    }

    public function component()
    {
        return $this->belonsTo(TenantPageComponent::class, 'component_id');
    }
}
