<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
        'is_late' => 'boolean',
        'grace_used' => 'boolean',
        'on_off_day' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function movements()
    {
        return $this->hasMany(Movement::class)->orderBy('out_at');
    }

    public function points()
    {
        return $this->hasMany(LocationPoint::class)->orderBy('recorded_at');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'logout_by');
    }

    /** Logged in on an earlier day and never logged out. */
    public function isPending(): bool
    {
        return $this->logout_at === null && $this->date->lt(today());
    }

    public function workedMinutes(): int
    {
        $end = $this->logout_at ?? ($this->isPending() ? $this->login_at : now());

        return max(0, (int) $this->login_at->diffInMinutes($end));
    }

    public function outsideMinutes(): int
    {
        return (int) $this->movements->sum(fn ($m) => $m->minutes());
    }
}
