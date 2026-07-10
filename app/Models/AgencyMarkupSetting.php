<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyMarkupSetting extends Model
{
    protected $table = 'agency_markup_settings';

    protected $fillable = [
        'tenant_id',
        'is_enabled',
        'markup_mode',
        'markup_value',
        'currency',
        'display_label',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'markup_value' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
