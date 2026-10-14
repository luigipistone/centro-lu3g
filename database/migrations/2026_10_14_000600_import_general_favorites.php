<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        $folders = DB::table('favorite_folders')->whereRaw('LOWER(name) = ?', ['generale'])->get();
        if ($folders->count() !== 1) {
            Log::warning('Importazione preferiti Generale non eseguita: cartella assente o ambigua.', [
                'folders_found' => $folders->count(),
            ]);
            echo "Importazione preferiti Generale saltata: cartelle trovate {$folders->count()}.\n";
            return;
        }

        $folder = $folders->first();
        $links = [
            ['Figma', 'https://www.figma.com/login', 'Marketing'],
            ['OpenAI Ads', 'https://ads.openai.com/it-IT', 'Marketing'],
            ['GitHub', 'https://github.com/', 'Servizi'],
            ['Google Ads', 'https://ads.google.com/aw/overview?ocid=219645566', 'Marketing'],
            ['Google Analytics', 'https://analytics.google.com/analytics/web/#/a11839860p289146667/reports/intelligenthome', 'Marketing'],
            ['Campaign URL Builder', 'https://ga-dev-tools.google/campaign-url-builder/', 'Marketing'],
            ['LinkedIn Ad Library', 'https://www.linkedin.com/ad-library', 'Marketing'],
            ['Meta Ad Library', 'https://www.facebook.com/ads/library/?active_status=all&ad_type=all&country=IT', 'Marketing'],
            ['Wordfence Central - Modelli', 'https://www.wordfence.com/central/templates', 'Servizi'],
            ['Gmail', 'https://mail.google.com/mail/u/0/', 'Azienda'],
            ['CapCut', 'https://www.capcut.com/it-it/', 'Marketing'],
            ['Stape', 'https://stape.io/login', 'Marketing'],
            ['Canva - Cartella', 'https://www.canva.com/folder/FAEjnhEjs0s', 'Marketing'],
            ['SaveMyLeads - Leadproxy', 'http://u1.savemyleads.com/leadproxy', 'Marketing'],
            ['PostPickr', 'https://next.postpickr.com/#/home', 'Marketing'],
            ['CartSan - Lavoratori Sapal', 'https://lavoratori.cartsan.it/sapalsrl/', 'Lavoro'],
        ];

        DB::transaction(function () use ($folder, $links) {
            $created = 0;
            $existing = 0;
            foreach ($links as [$title, $url, $categoryName]) {
                $category = DB::table('favorite_categories')->whereRaw('LOWER(name) = ?', [mb_strtolower($categoryName)])->first();
                if (! $category) {
                    $categoryId = (string) Str::uuid();
                    DB::table('favorite_categories')->insert([
                        'id' => $categoryId, 'name' => $categoryName, 'created_by' => $folder->created_by,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                } else {
                    $categoryId = $category->id;
                }

                $item = DB::table('favorite_items')->where('folder_id', $folder->id)
                    ->where(fn ($query) => $query->where('url', $url)->orWhereRaw('LOWER(title) = ?', [mb_strtolower($title)]))
                    ->first();
                if (! $item) {
                    $itemId = (string) Str::uuid();
                    DB::table('favorite_items')->insert([
                        'id' => $itemId, 'folder_id' => $folder->id, 'created_by' => $folder->created_by,
                        'title' => $title, 'url' => $url, 'description' => null, 'is_featured' => false,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $created++;
                } else {
                    $itemId = $item->id;
                    $existing++;
                }

                if (! DB::table('favorite_item_category')->where('item_id', $itemId)->where('category_id', $categoryId)->exists()) {
                    DB::table('favorite_item_category')->insert(['item_id' => $itemId, 'category_id' => $categoryId]);
                }
            }
            Log::info('Importazione preferiti Generale completata.', [
                'folder_id' => $folder->id, 'created' => $created, 'existing' => $existing,
            ]);
            echo "Importazione preferiti Generale: {$created} creati, {$existing} gia presenti.\n";
        });
    }

    public function down(): void
    {
        // Business bookmarks are not deleted during a schema rollback.
    }
};
