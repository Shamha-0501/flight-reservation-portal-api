<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantPages extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_pages';
    protected $fillable = [
        'tenant_id',
        'slug',
        'title',
        'schema',
        'seo',
        'status',
        'meta_title',
        'meta_description',
        'og_asset_id',
        'published_at'
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
