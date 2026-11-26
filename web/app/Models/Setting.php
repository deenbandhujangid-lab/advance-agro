<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = [];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    public const DEFAULTS = [
        'office_name' => 'Head Office',
        'office_lat' => '0',
        'office_lng' => '0',
        'office_radius' => '35',
        'grace_minutes' => '15',
        'grace_days' => '2',
        'ot_min_minutes' => '30',
        'ot_days' => '26',
        'cl_quota' => '12',
        'sl_quota' => '6',
    ];

    public static function get(string $key)
    {
        static $cache = null;
        if ($cache === null || app()->runningUnitTests()) {
            $cache = static::pluck('value', 'key')->all();
        }

        return $cache[$key] ?? (static::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
