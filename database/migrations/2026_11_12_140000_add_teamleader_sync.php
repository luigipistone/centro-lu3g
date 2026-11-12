<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('teamleader_settings', function (Blueprint $t) {
            $t->boolean('enabled')->default(false);
            $t->date('import_from')->nullable();
            $t->unsignedInteger('months_ahead')->default(12);
            $t->string('account_id')->nullable();
            $t->uuid('connected_by')->nullable();
            $t->dateTime('requested_at')->nullable();
            $t->dateTime('last_synced_at')->nullable();
            $t->dateTime('last_snapshot_at')->nullable();
            $t->text('last_error')->nullable();
            $t->text('last_summary')->nullable();
        });
        Schema::table('tasks', function (Blueprint $t) {
            $t->string('teamleader_account_id')->nullable()->index();
            $t->string('teamleader_meeting_id')->nullable();
            $t->string('teamleader_identity', 64)->nullable()->unique();
            $t->string('teamleader_revision', 64)->nullable();
            $t->dateTime('teamleader_starts_at')->nullable();
            $t->dateTime('teamleader_ends_at')->nullable();
            $t->dateTime('teamleader_cancelled_at')->nullable()->index();
        });
        Schema::create('teamleader_notices', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('task_id');
            $t->text('user_ids');
            $t->string('message');
            $t->dateTime('delivered_at')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('teamleader_notices');
        Schema::table('tasks', function (Blueprint $t) {
            $t->dropUnique(['teamleader_identity']);
            $t->dropColumn(['teamleader_account_id', 'teamleader_meeting_id', 'teamleader_identity', 'teamleader_revision', 'teamleader_starts_at', 'teamleader_ends_at', 'teamleader_cancelled_at']);
        });
        Schema::table('teamleader_settings', fn (Blueprint $t) => $t->dropColumn(['enabled', 'import_from', 'months_ahead', 'account_id', 'connected_by', 'requested_at', 'last_synced_at', 'last_snapshot_at', 'last_error', 'last_summary']));
    }
};
