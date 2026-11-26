<?php

namespace App\Services;

use App\Models\AdvanceEntry;
use App\Models\Attendance;
use App\Models\Notice;
use App\Models\SalarySheet;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use DomainException;

/** Monthly salary = Basic - unpaid days amount + overtime amount - advance deduction. */
class SalaryService
{
    public static function isFinalized(string $month): bool
    {
        return SalarySheet::where('month', $month)->whereNotNull('finalized_at')->exists();
    }

    /** Build (or rebuild) the draft sheet for a month. */
    public static function generate(string $month): void
    {
        if (self::isFinalized($month)) {
            throw new DomainException('Salary for this month is already finalized.');
        }
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        if ($start->gt(today())) {
            throw new DomainException('This month has not started yet.');
        }

        $pending = Attendance::with('user')->whereNull('logout_at')->whereDate('date', '<', today())
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get();
        if ($pending->isNotEmpty()) {
            $names = $pending->map(fn ($a) => $a->user->name.' ('.$a->date->format('d M').')')->unique()->implode(', ');
            throw new DomainException('Close these pending logouts first: '.$names);
        }

        $users = User::where('role', '!=', 'super_admin')->where('status', 'active')->where('basic_salary', '>', 0)->get();
        SalarySheet::where('month', $month)->whereNotIn('user_id', $users->pluck('id'))->delete();

        foreach ($users as $user) {
            $totals = AttendanceService::month($user, $month)['totals'];
            $basic = $user->basic_salary;
            $unpaidAmount = round($basic / $start->daysInMonth * $totals['unpaid_days'], 2);
            $hourly = $basic / ((int) Setting::get('ot_days') * $user->shiftHours());
            $otAmount = round($totals['ot_minutes'] / 60 * $hourly, 2);
            $gross = max(0, $basic - $unpaidAmount + $otAmount);
            $deduction = round(min($user->deduction_per_month, $user->dueAmount(), $gross), 2);

            SalarySheet::updateOrCreate(['user_id' => $user->id, 'month' => $month], [
                'basic' => $basic, 'days_in_month' => $start->daysInMonth,
                'unpaid_days' => $totals['unpaid_days'], 'unpaid_amount' => $unpaidAmount,
                'ot_minutes' => $totals['ot_minutes'], 'ot_amount' => $otAmount,
                'advance_deduction' => $deduction, 'net' => round($gross - $deduction, 2),
            ]);
        }
    }

    /** Lock the month and recover the advance deductions. */
    public static function finalize(string $month, User $by): void
    {
        $sheets = SalarySheet::where('month', $month)->get();
        if ($sheets->isEmpty()) {
            throw new DomainException('Generate the salary sheet first.');
        }
        if (self::isFinalized($month)) {
            throw new DomainException('Salary for this month is already finalized.');
        }
        $label = Carbon::parse($month.'-01')->format('M Y');
        foreach ($sheets as $sheet) {
            if ($sheet->advance_deduction > 0) {
                AdvanceEntry::create([
                    'user_id' => $sheet->user_id, 'date' => today()->toDateString(), 'type' => 'deduction',
                    'amount' => $sheet->advance_deduction, 'remark' => 'Salary '.$label, 'created_by' => $by->id,
                ]);
            }
            $sheet->update(['finalized_at' => now()]);
            Notice::send($sheet->user_id, 'Salary ready', 'Your salary for '.$label.' is Rs '.number_format($sheet->net, 2).'.');
        }
    }
}
