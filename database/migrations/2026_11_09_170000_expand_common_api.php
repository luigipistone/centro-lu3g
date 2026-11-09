<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_connections', function (Blueprint $t) {
            $t->string('kind', 20)->default('page_notes');
            $t->json('access_policy')->nullable();
            $t->uuid('client_id')->nullable()->change();
            $t->uuid('project_id')->nullable()->change();
        });
        Schema::create('api_subscriptions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('connection_id')->constrained('api_connections')->cascadeOnDelete();
            $t->string('url', 1000);
            $t->text('secret');
            $t->json('events');
            $t->boolean('active')->default(true);
            $t->timestamp('initialized_at')->nullable();
            $t->timestamps();
        });
        Schema::create('api_subscription_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('subscription_id')->constrained('api_subscriptions')->cascadeOnDelete();
            $t->string('resource', 30);
            $t->uuid('resource_id');
            $t->string('hash', 64);
            $t->json('context');
            $t->uuid('scan_id');
            $t->timestamps();
            $t->unique(['subscription_id', 'resource', 'resource_id'], 'api_snapshot_resource_unique');
        });
        Schema::create('api_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('subscription_id')->constrained('api_subscriptions')->cascadeOnDelete();
            $t->string('event', 60);
            $t->json('context');
            $t->longText('payload');
            $t->string('status', 20)->default('pending')->index();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->timestamp('next_attempt_at')->index();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['api_events', 'api_subscription_snapshots', 'api_subscriptions'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('api_connections', fn (Blueprint $t) => $t->dropColumn(['kind', 'access_policy']));
    }
};
