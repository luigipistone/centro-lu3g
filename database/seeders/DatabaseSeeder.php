<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['SOCIAL', '#0ea5e9'],
            ['NEWSLETTER', '#22c55e'],
            ['SEO', '#a855f7'],
            ['ADV', '#f97316'],
        ] as [$name, $color]) {
            DB::table('services')->updateOrInsert(
                ['name' => $name],
                [
                    'id' => (string) str()->uuid(),
                    'color' => $color,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

    }
}
