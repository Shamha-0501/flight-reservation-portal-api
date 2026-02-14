<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    use SoftDeletes;

    protected $table = 'pbac_permission';
    protected $fillable = [
        'action_id',
        'resource_id',
        'key',
        'label'
    ];

    public function action()
    {
        return $this->belongsTo(Action::class, 'action_id');
    }

    public function resource()
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }
}
