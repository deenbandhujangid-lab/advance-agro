<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutsidePermission extends Model
{
    protected $guarded = [];

    protected $casts = ['date' => 'date', 'allow_outside_logout' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
