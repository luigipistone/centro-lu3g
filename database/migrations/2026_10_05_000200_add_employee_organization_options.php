<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('contract_level')->nullable()->after('job_title');
        });

        Schema::create('employee_organization_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 20);
            $table->string('name');
            $table->timestamps();
            $table->unique(['type', 'name']);
        });

        foreach (['department' => 'department', 'office' => 'office'] as $field => $type) {
            DB::table('profiles')->whereNotNull($field)->distinct()->pluck($field)->each(function ($name) use ($type) {
                $name = trim((string) $name);
                if ($name === '') return;
                DB::table('employee_organization_options')->insertOrIgnore([
                    'id' => (string) Str::uuid(), 'type' => $type, 'name' => $name,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_organization_options');
        Schema::table('profiles', fn (Blueprint $table) => $table->dropColumn('contract_level'));
    }
};
