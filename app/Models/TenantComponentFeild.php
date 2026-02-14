<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantComponentFeild extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_component_fields';
    protected $fillable = [
        'tenant_id',
        'component_id',
        'field_key',
        'field_type',
        'value_string',
        'value_text',
        'value_int',
        'value_bool',
        'value_decimal',
        'valuet_asset_id'
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function component()
    {
        return $this->belongsTo(TenantPageComponent::class, 'component_id');
    }

    public function asset()
    {
        return $this->belongsTo(TenantAssets::class, 'value_asset_id');
    }
}
