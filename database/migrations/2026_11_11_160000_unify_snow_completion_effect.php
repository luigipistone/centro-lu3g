<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('profiles')->where('completion_effect', 'snowfall')->update(['completion_effect' => 'snow']);
    }

    public function down(): void
    {
        // Both versions share the existing snow preference; no data needs reverting.
    }
};
