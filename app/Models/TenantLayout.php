<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantLayout extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_layouts';
    protected $fillable = [
        'tenant_id',
        'header',
        'nav',
        'footer'
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
