<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    protected $guarded = [];

    protected $casts = ['read_at' => 'datetime'];

    public static function send(?int $userId, string $title, ?string $body = null): void
    {
        if ($userId) {
            static::create(['user_id' => $userId, 'title' => $title, 'body' => $body]);
        }
    }
}
