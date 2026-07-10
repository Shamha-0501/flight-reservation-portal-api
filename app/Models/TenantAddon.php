<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantAddon extends Model
{
    protected $table = 'tenant_addons';

    protected $fillable = [
        'tenant_id',
        'addon_id',
        'is_enabled',
        'display_name',
        'display_description',
        'price',
        'currency',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(PlatformAddon::class, 'addon_id');
    }
}
