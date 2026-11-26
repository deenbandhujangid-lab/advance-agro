<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movement extends Model
{
    protected $guarded = [];

    protected $casts = ['out_at' => 'datetime', 'in_at' => 'datetime', 'allowed' => 'boolean'];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function minutes(): int
    {
        return max(0, (int) $this->out_at->diffInMinutes($this->in_at ?? now()));
    }
}
