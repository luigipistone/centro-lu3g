<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('google_calendar_settings', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
            $t->string('client_id')->nullable();
            $t->text('client_secret')->nullable();
            $t->text('refresh_token')->nullable();
            $t->string('calendar_id')->nullable();
            $t->string('calendar_name')->nullable();
            $t->string('timezone')->default('Europe/Rome');
            $t->date('import_from')->nullable();
            $t->unsignedSmallInteger('months_ahead')->default(12);
            $t->boolean('enabled')->default(false);
            $t->uuid('connected_by')->nullable();
            $t->text('sync_token')->nullable();
            $t->dateTime('requested_at')->nullable();
            $t->dateTime('last_synced_at')->nullable();
            $t->dateTime('last_snapshot_at')->nullable();
            $t->text('last_error')->nullable();
            $t->text('last_summary')->nullable();
            $t->uuid('channel_id')->nullable();
            $t->string('channel_token')->nullable();
            $t->string('resource_id')->nullable();
            $t->dateTime('channel_expires_at')->nullable();
            $t->text('watch_error')->nullable();
            $t->timestamps();
        });
        Schema::table('tasks', function (Blueprint $t) {
            $t->string('google_calendar_id')->nullable()->index();
            $t->text('google_event_id')->nullable();
            $t->string('google_identity', 64)->nullable()->unique();
            $t->text('google_event_url')->nullable();
            $t->text('google_meet_url')->nullable();
            $t->dateTime('google_starts_at')->nullable();
            $t->dateTime('google_ends_at')->nullable();
            $t->boolean('google_all_day')->default(false);
            $t->dateTime('google_cancelled_at')->nullable()->index();
            $t->string('google_revision', 64)->nullable();
        });
        Schema::create('google_calendar_notices', function (Blueprint $t) {
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
        Schema::dropIfExists('google_calendar_notices');
        Schema::table('tasks', function (Blueprint $t) {
            $t->dropUnique(['google_identity']);
            $t->dropColumn(['google_calendar_id', 'google_event_id', 'google_identity', 'google_event_url', 'google_meet_url', 'google_starts_at', 'google_ends_at', 'google_all_day', 'google_cancelled_at', 'google_revision']);
        });
        Schema::dropIfExists('google_calendar_settings');
    }
};
