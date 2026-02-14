<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantBranding extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_branding';
    protected $fillable = [
        'tenant_id',
        'brand_name',
        'site_title',
        'logo_light_asset_id',
        'logo_dark_asset_id',
        'favicon_asset_id',
        'default_og_asset_id',
        'primary_color',
        'secondary_color',
        'accent_color',
        'font_family',
        'support_email',
        'support_phone',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function logoLight()
    {
        return $this->belongsTo(TenantAssets::class, 'logo_light_asset_id');
    }

    public function logoDark()
    {
        return $this->belongsTo(TenantAssets::class, 'logo_dark_asset_id');
    }

    public function defaultOg()
    {
        return $this->belongsTo(TenantAssets::class, 'default_og_asset_id');
    }

    public function favicon()
    {
        return $this->belongsTo(TenantAssets::class, 'default_og_asset_id');
    }
}
