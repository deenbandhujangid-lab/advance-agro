<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalarySheet extends Model
{
    protected $guarded = [];

    protected $casts = ['finalized_at' => 'datetime', 'basic' => 'float', 'unpaid_days' => 'float',
        'unpaid_amount' => 'float', 'ot_amount' => 'float', 'advance_deduction' => 'float', 'net' => 'float'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
