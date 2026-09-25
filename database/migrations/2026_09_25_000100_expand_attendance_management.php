<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absence_requests', function (Blueprint $table) {
            $table->text('decision_reason')->nullable();
            $table->text('integration_request')->nullable();
            $table->string('cause_code')->nullable();
            $table->uuid('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
        });

        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('default_daily_minutes')->default(480);
            $table->json('working_days')->nullable();
            $table->json('approvers')->nullable();
            $table->timestamps();
        });
        Schema::create('attendance_holidays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('day')->unique();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('attendance_causes', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('name');
            $table->boolean('reduces_presence')->default(true);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('attendance_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->unsignedSmallInteger('year');
            $table->string('type');
            $table->integer('allocated_minutes')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'year', 'type']);
        });
        Schema::create('attendance_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->date('day');
            $table->string('cause');
            $table->integer('minutes')->default(0);
            $table->text('note')->nullable();
            $table->uuid('created_by');
            $table->timestamps();
            $table->unique(['user_id', 'day', 'cause']);
            $table->index(['day', 'cause']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_entries');
        Schema::dropIfExists('attendance_balances');
        Schema::dropIfExists('attendance_causes');
        Schema::dropIfExists('attendance_holidays');
        Schema::dropIfExists('attendance_settings');
        Schema::table('absence_requests', function (Blueprint $table) {
            $table->dropColumn(['decision_reason', 'integration_request', 'cause_code', 'decided_by', 'decided_at']);
        });
    }
};
