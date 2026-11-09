<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_connections', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('site', 500);
            $t->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $t->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $t->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $t->json('scopes');
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
        });
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->string('tokenable_type');
            $t->uuid('tokenable_id');
            $t->index(['tokenable_type', 'tokenable_id']);
            $t->string('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable()->index();
            $t->timestamps();
        });
        Schema::create('api_resource_links', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('connection_id')->constrained('api_connections')->cascadeOnDelete();
            $t->string('kind', 20);
            $t->string('parent_key', 36)->default('');
            $t->string('external_ref', 190);
            $t->uuid('resource_id');
            $t->string('request_hash', 64);
            $t->string('snapshot_hash', 64)->nullable();
            $t->json('metadata')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['connection_id', 'kind', 'parent_key', 'external_ref'], 'api_resource_external_unique');
            $t->unique(['connection_id', 'kind', 'resource_id'], 'api_resource_native_unique');
        });
        Schema::create('api_webhooks', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('connection_id')->constrained('api_connections')->cascadeOnDelete();
            $t->foreignUuid('task_id')->constrained('tasks')->cascadeOnDelete();
            $t->string('url', 1000);
            $t->text('secret');
            $t->json('events');
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['connection_id', 'task_id']);
        });
        Schema::create('api_webhook_deliveries', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('webhook_id')->constrained('api_webhooks')->cascadeOnDelete();
            $t->longText('payload');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->string('status', 20)->default('pending');
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->timestamp('next_attempt_at')->index();
            $t->timestamps();
        });
        Schema::create('api_request_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('connection_id')->constrained('api_connections')->cascadeOnDelete();
            $t->string('method', 10);
            $t->string('path', 500);
            $t->unsignedSmallInteger('status');
            $t->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        foreach (['api_request_logs', 'api_webhook_deliveries', 'api_webhooks', 'api_resource_links', 'personal_access_tokens', 'api_connections'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
