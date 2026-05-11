<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserRole extends Model
{
    use SoftDeletes;

    protected $table = 'pbac_roles';
    protected $fillable = [
        'tenant_id',
        'key',
        'name',
        'scope',
        'is_external',
        'description',
    ];
}
