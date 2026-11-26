<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('emp_code', 30)->unique()->after('id');
            $t->string('phone', 15)->nullable()->after('email');
            $t->string('role', 20)->default('employee');          // super_admin, manager, account_manager, employee
            $t->string('emp_type', 20)->nullable();               // sales, operation
            $t->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('device_id')->nullable();                  // registered phone
            $t->string('status', 10)->default('active');          // active, inactive
            $t->date('joined_on')->nullable();
            $t->time('morning_time')->default('09:30:00');
            $t->time('evening_time')->default('18:30:00');
            $t->string('sunday_rule', 5)->default('all');         // all, two
            $t->string('off_sundays', 20)->default('2,4');        // used when sunday_rule = two
            $t->boolean('ot_allowed')->default(true);
            $t->decimal('basic_salary', 12, 2)->default(0);
            $t->decimal('deduction_per_month', 12, 2)->default(0);
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->string('value')->nullable();
        });

        Schema::create('attendances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->dateTime('login_at');
            $t->dateTime('logout_at')->nullable();
            $t->decimal('login_lat', 10, 7)->nullable();
            $t->decimal('login_lng', 10, 7)->nullable();
            $t->decimal('logout_lat', 10, 7)->nullable();
            $t->decimal('logout_lng', 10, 7)->nullable();
            $t->boolean('is_late')->default(false);
            $t->boolean('grace_used')->default(false);
            $t->boolean('on_off_day')->default(false);
            $t->integer('ot_minutes')->default(0);
            $t->foreignId('logout_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('remark')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'date']);
        });

        Schema::create('movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->dateTime('out_at');
            $t->dateTime('in_at')->nullable();
            $t->boolean('allowed')->default(false);
            $t->timestamps();
        });

        Schema::create('location_points', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $t->decimal('lat', 10, 7);
            $t->decimal('lng', 10, 7);
            $t->boolean('inside')->default(true);
            $t->dateTime('recorded_at');
        });

        Schema::create('outside_permissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->time('from_time');
            $t->time('to_time');
            $t->string('reason')->nullable();
            $t->boolean('allow_outside_logout')->default(false);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('leaves', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('type', 5);                                // CL, SL, CCL, LWP
            $t->date('from_date');
            $t->date('to_date');
            $t->boolean('half_day')->default(false);
            $t->decimal('days', 5, 1)->default(0);
            $t->string('reason')->nullable();
            $t->string('status', 12)->default('pending');         // pending, approved, rejected, cancelled
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('decide_remark')->nullable();
            $t->timestamps();
        });

        Schema::create('ccl_credits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->date('earned_date');
            $t->string('status', 12)->default('pending');         // pending, approved, rejected
            $t->timestamps();
            $t->unique(['user_id', 'earned_date']);
        });

        Schema::create('holidays', function (Blueprint $t) {
            $t->id();
            $t->date('date')->unique();
            $t->string('name');
        });

        Schema::create('advance_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->string('type', 12);                               // advance, deduction, repayment
            $t->decimal('amount', 12, 2);
            $t->string('remark')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('salary_sheets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('month', 7);                               // YYYY-MM
            $t->decimal('basic', 12, 2);
            $t->integer('days_in_month');
            $t->decimal('unpaid_days', 5, 1)->default(0);
            $t->decimal('unpaid_amount', 12, 2)->default(0);
            $t->integer('ot_minutes')->default(0);
            $t->decimal('ot_amount', 12, 2)->default(0);
            $t->decimal('advance_deduction', 12, 2)->default(0);
            $t->decimal('net', 12, 2)->default(0);
            $t->dateTime('finalized_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'month']);
        });

        Schema::create('notices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->string('body')->nullable();
            $t->dateTime('read_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notices', 'salary_sheets', 'advance_entries', 'holidays', 'ccl_credits', 'leaves',
                  'outside_permissions', 'location_points', 'movements', 'attendances', 'settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
