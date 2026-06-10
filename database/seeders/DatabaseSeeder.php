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

        foreach (['preventivo' => 'PREV-', 'proforma' => 'PRO-', 'fattura' => 'FAT-', 'nota_credito' => 'NC-'] as $type => $prefix) {
            DB::table('document_numbering')->updateOrInsert(
                ['doc_type' => $type, 'year' => now()->year],
                [
                    'id' => (string) str()->uuid(),
                    'prefix' => $prefix,
                    'format' => '{prefix}{year}/{seq}',
                    'current_seq' => 0,
                    'yearly_reset' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        DB::table('document_settings')->updateOrInsert(
            ['company_name' => 'Centro LU3G'],
            [
                'id' => (string) str()->uuid(),
                'country' => 'Italia',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
