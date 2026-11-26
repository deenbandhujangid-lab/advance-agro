<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Leave extends Model
{
    protected $guarded = [];

    protected $casts = ['from_date' => 'date', 'to_date' => 'date', 'half_day' => 'boolean', 'days' => 'float'];

    public const TYPES = ['CL' => 'Casual leave', 'SL' => 'Sick leave', 'CCL' => 'CCL', 'LWP' => 'Leave without pay'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
