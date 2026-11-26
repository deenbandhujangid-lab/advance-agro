<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CclCredit extends Model
{
    protected $guarded = [];

    protected $casts = ['earned_date' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
