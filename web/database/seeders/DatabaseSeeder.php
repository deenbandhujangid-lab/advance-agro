<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** First-time data: the Super Admin login and the default rules. */
    public function run(): void
    {
        User::firstOrCreate(['emp_code' => 'ADMIN'], [
            'name' => 'Super Admin', 'email' => 'admin@advanceagro.local', 'password' => 'admin123',
            'role' => 'super_admin', 'status' => 'active', 'joined_on' => today(),
        ]);

        foreach (Setting::DEFAULTS as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
