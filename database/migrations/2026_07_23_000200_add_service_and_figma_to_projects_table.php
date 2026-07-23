<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->uuid('service_id')->nullable()->after('client_id');
            $table->string('figma_url', 2048)->nullable()->after('description');

            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
            $table->index('service_id');
        });

        if (Schema::hasTable('wordpress_provisionings')) {
            $webServiceId = DB::table('services')
                ->whereRaw('LOWER(name) LIKE ?', ['%web%'])
                ->orderBy('name')
                ->value('id');

            if ($webServiceId) {
                DB::table('projects')
                    ->whereNull('service_id')
                    ->whereIn('id', DB::table('wordpress_provisionings')->select('project_id'))
                    ->update(['service_id' => $webServiceId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropIndex(['service_id']);
            $table->dropColumn(['service_id', 'figma_url']);
        });
    }
};
