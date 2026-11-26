<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'joined_on' => 'date',
            'ot_allowed' => 'boolean',
            'basic_salary' => 'float',
            'deduction_per_month' => 'float',
        ];
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function advanceEntries()
    {
        return $this->hasMany(AdvanceEntry::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isAccountManager(): bool
    {
        return $this->role === 'account_manager';
    }

    public function isSales(): bool
    {
        return $this->emp_type === 'sales';
    }

    public function roleLabel(): string
    {
        return ['super_admin' => 'Super Admin', 'manager' => 'Manager',
                'account_manager' => 'Account Manager', 'employee' => 'Employee'][$this->role] ?? $this->role;
    }

    /** Shift length in hours. */
    public function shiftHours(): float
    {
        $minutes = (strtotime($this->evening_time) - strtotime($this->morning_time)) / 60;

        return max(1, $minutes / 60);
    }

    /** Advance still to be recovered. */
    public function dueAmount(): float
    {
        $given = (float) $this->advanceEntries()->where('type', 'advance')->sum('amount');
        $back = (float) $this->advanceEntries()->whereIn('type', ['deduction', 'repayment'])->sum('amount');

        return round(max(0, $given - $back), 2);
    }

    /** Employees this user is allowed to see: everyone, or only own team for a manager. */
    public function scopeVisibleTo($query, User $viewer)
    {
        $query->where('role', '!=', 'super_admin');
        if ($viewer->isManager()) {
            $query->where('manager_id', $viewer->id);
        }

        return $query;
    }
}
