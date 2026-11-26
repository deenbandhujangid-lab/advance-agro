<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'show'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'role:super_admin,manager,account_manager'])->group(function () {
    // Everyone on the web can look.
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/attendance', [AttendanceController::class, 'daily'])->name('attendance.daily');
    Route::get('/attendance/monthly', [AttendanceController::class, 'monthly'])->name('attendance.monthly');
    Route::get('/attendance/day/{attendance}', [AttendanceController::class, 'show'])->name('attendance.show');
    Route::get('/leave', [LeaveController::class, 'index'])->name('leave.index');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/password', [AuthController::class, 'password'])->name('password');
    Route::post('/password', [AuthController::class, 'updatePassword']);

    // Super Admin and Manager act on the team.
    Route::middleware('role:super_admin,manager')->group(function () {
        Route::post('/attendance/day/{attendance}/correct', [AttendanceController::class, 'correct'])->name('attendance.correct');
        Route::get('/attendance/pending', [AttendanceController::class, 'pending'])->name('attendance.pending');
        Route::post('/attendance/pending/{attendance}', [AttendanceController::class, 'closePending'])->name('attendance.close');
        Route::get('/attendance/outside', [AttendanceController::class, 'outside'])->name('attendance.outside');
        Route::post('/attendance/outside', [AttendanceController::class, 'storeOutside'])->name('attendance.outside.store');
        Route::delete('/attendance/outside/{permission}', [AttendanceController::class, 'deleteOutside'])->name('attendance.outside.delete');
        Route::post('/leave/{leave}/decide', [LeaveController::class, 'decide'])->name('leave.decide');
        Route::post('/leave/ccl/{credit}/decide', [LeaveController::class, 'decideCcl'])->name('leave.ccl');
    });

    // Super Admin and Account Manager handle money.
    Route::middleware('role:super_admin,account_manager')->group(function () {
        Route::get('/salary/advances', [SalaryController::class, 'advances'])->name('salary.advances');
        Route::get('/salary/advances/{employee}', [SalaryController::class, 'statement'])->name('salary.statement');
        Route::post('/salary/advances/{employee}', [SalaryController::class, 'storeEntry'])->name('salary.entry');
        Route::post('/salary/deduction/{employee}', [SalaryController::class, 'setDeduction'])->name('salary.deduction');
        Route::get('/salary/sheet', [SalaryController::class, 'sheet'])->name('salary.sheet');
        Route::post('/salary/generate', [SalaryController::class, 'generate'])->name('salary.generate');
        Route::post('/salary/finalize', [SalaryController::class, 'finalize'])->name('salary.finalize');
    });

    // Only the Super Admin changes employees and rules.
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/employees/new', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::post('/employees/{employee}/reset-phone', [EmployeeController::class, 'resetDevice'])->name('employees.reset');
        Route::post('/employees/{employee}/toggle', [EmployeeController::class, 'toggle'])->name('employees.toggle');
        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/holidays', [SettingController::class, 'storeHoliday'])->name('holidays.store');
        Route::delete('/settings/holidays/{holiday}', [SettingController::class, 'deleteHoliday'])->name('holidays.delete');
    });
});
