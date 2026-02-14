<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resource extends Model
{
    use SoftDeletes;

    protected $table = 'pbac_resources';
    protected $fillable = [
        'resource',
        'group'
    ];
}
