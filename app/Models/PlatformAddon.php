<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformAddon extends Model
{
    protected $table = 'addons';

    protected $fillable = [
        'code',
        'default_name',
        'default_description',
        'category',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tenantAddons(): HasMany
    {
        return $this->hasMany(TenantAddon::class, 'addon_id');
    }

    public function bookingAddons(): HasMany
    {
        return $this->hasMany(BookingAddon::class, 'addon_id');
    }
}
