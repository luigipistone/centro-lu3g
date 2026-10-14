<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        $folders = DB::table('favorite_folders')
            ->whereRaw('LOWER(name) = ?', ['amministrazione'])
            ->get();

        if ($folders->count() !== 1) {
            Log::warning('Importazione preferiti Amministrazione non eseguita: cartella assente o ambigua.', [
                'folders_found' => $folders->count(),
            ]);
            echo "Importazione preferiti Amministrazione saltata: cartelle trovate {$folders->count()}.\n";
            return;
        }

        $folder = $folders->first();
        $links = [
            ['Impresa Italia', 'https://impresa.italia.it/cadi/app/login', 'Azienda'],
            ['Fastweb Energia', 'https://www.fastweb.it/myfastweb-energia/login/', 'Servizi'],
            ['Credem Aziende', 'https://accesso-aziende.credem.it/mga/sps/authsvc', 'Finanza'],
            ['Fabrick Pass', 'https://www.fabrickpass.com/login', 'Finanza'],
            ['Banco BPM YouBusiness Web', 'https://youbusinessweb.bancobpm.it/Youbiz.Web/Security/Sec', 'Finanza'],
            ['Sportello Cloud', 'https://www.sportello.cloud/', 'Azienda'],
            ['Sherweb Cumulus', 'https://cumulus.sherweb.com/partners/lu3g-srl', 'Servizi'],
            ['Randstad Professional Portal', 'https://randstad-de-01.my.site.com/professionalportal/s/login/', 'Lavoro'],
            ['Teamleader Planning', 'https://focus.teamleader.eu/planning.php', 'Azienda'],
            ['Teamleader Fatturazione Elettronica', 'https://teamleadercrm.youdox.it/', 'Azienda'],
            ['Stripe Dashboard', 'https://dashboard.stripe.com/', 'Finanza'],
            ['PayPal Business', 'https://www.paypal.com/mep/dashboard', 'Finanza'],
            ['Serverlet 4e177', 'https://shde-4e177.serverlet.com/login_up.php', 'Servizi'],
            ['Serverlet 2eaae', 'https://shde-2eaae.serverlet.com/', 'Servizi'],
            ['Meta Ads - Fatture', 'https://adsmanager.facebook.com/adsmanager/billing_hub/invoices/?business_id=909030486249326&asset_id=1194379725018246&payment_account_id=1194379725018246', 'Marketing'],
            ['Google Partners Rewards', 'https://partnersrewards.withgoogle.com/', 'Marketing'],
            ['Sortlist LU3G', 'https://app.sortlist.com/lu3g-la-boutique-del-web/dashboard', 'Marketing'],
            ['CartSan - Sapal', 'https://sapalsrl.cartsan.it/#/login', 'Servizi'],
            ['ALD Automotive', 'https://ald.mobilitysolutions.it/', 'Azienda'],
            ['Adobe Admin Console', 'https://adminconsole.adobe.com/2D261E67662F4C4D0A495CB4@AdobeOrg/overview', 'Documenti'],
            ['Namirial TSP', 'https://portal.namirialtsp.com/index', 'Documenti'],
            ['Namirial Sign', 'https://sign.namirial.app/', 'Documenti'],
            ['Fatture', 'https://drive.google.com/drive/u/0/folders/1Q_Sb0_RFqhk6Cck1pI_ANazyQi6W6UpG', 'Documenti'],
            ['Regali Natale', 'https://drive.google.com/drive/u/0/folders/1SuJxZ0bVDyDlayr-JDQO4-9JSoTsvvUB', 'Documenti'],
        ];

        DB::transaction(function () use ($folder, $links) {
            $created = 0;
            $existing = 0;
            foreach ($links as [$title, $url, $categoryName]) {
                $category = DB::table('favorite_categories')->whereRaw('LOWER(name) = ?', [mb_strtolower($categoryName)])->first();
                if (! $category) {
                    $categoryId = (string) Str::uuid();
                    DB::table('favorite_categories')->insert([
                        'id' => $categoryId, 'name' => $categoryName,
                        'created_by' => $folder->created_by, 'created_at' => now(), 'updated_at' => now(),
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
            Log::info('Importazione preferiti Amministrazione completata.', [
                'folder_id' => $folder->id, 'created' => $created, 'existing' => $existing,
            ]);
            echo "Importazione preferiti Amministrazione: {$created} creati, {$existing} gia presenti.\n";
        });
    }

    public function down(): void
    {
        // Business bookmarks are not deleted during a schema rollback.
    }
};
