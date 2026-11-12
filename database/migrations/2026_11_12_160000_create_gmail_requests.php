<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gmail_settings', function (Blueprint $t) {
            $t->id(); $t->string('client_id'); $t->text('client_secret'); $t->timestamps();
        });
        Schema::create('gmail_connections', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->uuid('user_id')->unique(); $t->string('email')->unique();
            $t->text('refresh_token')->nullable(); $t->string('label_id')->nullable(); $t->string('label_name')->nullable();
            $t->boolean('enabled')->default(false); $t->timestamp('last_synced_at')->nullable();
            $t->text('last_error')->nullable(); $t->timestamp('requested_at')->nullable(); $t->timestamps();
            $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
        Schema::create('mail_requests', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->uuid('connection_id'); $t->string('thread_id');
            $t->string('subject'); $t->string('sender')->nullable(); $t->string('status')->default('pending');
            $t->uuid('reviewer_id')->nullable(); $t->uuid('task_id')->nullable(); $t->uuid('duplicate_of')->nullable();
            $t->uuid('suggested_client_id')->nullable(); $t->uuid('suggested_project_id')->nullable();
            $t->unsignedInteger('version')->default(1); $t->boolean('has_updates')->default(false); $t->timestamps();
            $t->unique(['connection_id', 'thread_id']); $t->index(['status', 'updated_at']);
        });
        Schema::create('mail_request_messages', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->uuid('request_id'); $t->uuid('connection_id'); $t->string('gmail_id');
            $t->string('message_hash', 64)->index(); $t->string('sender')->nullable(); $t->string('subject');
            $t->longText('body'); $t->json('attachments'); $t->timestamp('received_at')->nullable(); $t->timestamps();
            $t->unique(['connection_id', 'gmail_id']);
            $t->foreign('request_id')->references('id')->on('mail_requests')->cascadeOnDelete();
        });
        DB::table('role_permissions')->insert(['id' => (string) Str::uuid(), 'role' => 'admin', 'permission' => 'requests.manage', 'allowed' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
    public function down(): void
    {
        Schema::dropIfExists('mail_request_messages'); Schema::dropIfExists('mail_requests');
        Schema::dropIfExists('gmail_connections'); Schema::dropIfExists('gmail_settings');
        DB::table('role_permissions')->where('permission', 'requests.manage')->delete();
    }
};
