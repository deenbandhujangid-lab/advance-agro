<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['date' => 'date'];
}
