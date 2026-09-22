<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('full_name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('department')->nullable()->after('job_title');
            $table->foreignUuid('manager_user_id')->nullable()->after('department')->constrained('users')->nullOnDelete();
            $table->string('office')->nullable()->after('manager_user_id');
            $table->string('employment_status')->default('active')->after('office');
            $table->date('hire_date')->nullable()->after('employment_status');
            $table->date('termination_date')->nullable()->after('hire_date');
            $table->decimal('weekly_hours', 5, 2)->nullable()->after('termination_date');
            $table->boolean('part_time')->default(false)->after('weekly_hours');
            $table->unsignedTinyInteger('part_time_percentage')->nullable()->after('part_time');
            $table->json('work_schedule')->nullable()->after('part_time_percentage');
            $table->json('smartworking_days')->nullable()->after('smartworking_day');
            $table->text('smartworking_rules')->nullable()->after('smartworking_days');
        });

        DB::table('profiles')->orderBy('id')->get(['id', 'full_name', 'smartworking_day'])->each(function ($profile) {
            $parts = preg_split('/\s+/', trim((string) $profile->full_name), 2);
            DB::table('profiles')->where('id', $profile->id)->update([
                'first_name' => $parts[0] ?? null,
                'last_name' => $parts[1] ?? null,
                'smartworking_days' => $profile->smartworking_day ? json_encode([$profile->smartworking_day]) : null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropForeign(['manager_user_id']);
            $table->dropColumn(['first_name', 'last_name', 'department', 'manager_user_id', 'office', 'employment_status', 'hire_date', 'termination_date', 'weekly_hours', 'part_time', 'part_time_percentage', 'work_schedule', 'smartworking_days', 'smartworking_rules']);
        });
    }
};
