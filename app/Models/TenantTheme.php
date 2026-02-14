<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantTheme extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_themes';
    protected $fillable = [
        'tenant_id',
        'mode_default',
        'tokens',
        'custom_css'
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
