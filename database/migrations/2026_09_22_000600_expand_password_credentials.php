<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('password_items', function (Blueprint $table) {
            $table->string('category', 60)->default('other')->after('title')->index();
            $table->string('subcategory', 100)->nullable()->after('category')->index();
            $table->json('category_data')->nullable()->after('custom_fields');
            $table->string('credential_status', 30)->default('active')->after('favorite')->index();
            $table->string('mfa_status', 30)->default('unknown')->after('credential_status')->index();
            $table->unsignedSmallInteger('password_length')->nullable()->after('mfa_status');
            $table->unsignedTinyInteger('strength_score')->nullable()->after('password_length');
            $table->string('password_fingerprint', 64)->nullable()->after('strength_score')->index();
            $table->timestamp('password_changed_at')->nullable()->after('password_fingerprint');
        });

        DB::table('password_items')->whereNull('password_changed_at')->update([
            'password_changed_at' => DB::raw('COALESCE(updated_at, created_at)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('password_items', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropIndex(['subcategory']);
            $table->dropIndex(['credential_status']);
            $table->dropIndex(['mfa_status']);
            $table->dropIndex(['password_fingerprint']);
            $table->dropColumn(['category', 'subcategory', 'category_data', 'credential_status', 'mfa_status', 'password_length', 'strength_score', 'password_fingerprint', 'password_changed_at']);
        });
    }
};
