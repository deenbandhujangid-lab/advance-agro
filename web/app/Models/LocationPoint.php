<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationPoint extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['recorded_at' => 'datetime', 'inside' => 'boolean', 'lat' => 'float', 'lng' => 'float'];
}
