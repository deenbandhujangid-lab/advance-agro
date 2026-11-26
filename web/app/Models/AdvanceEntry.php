<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvanceEntry extends Model
{
    protected $guarded = [];

    protected $casts = ['date' => 'date', 'amount' => 'float'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
