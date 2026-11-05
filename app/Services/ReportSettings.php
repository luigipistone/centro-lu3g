<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportSettings
{
    public function token(): ?string
    {
        $encrypted = Schema::hasTable('report_settings')
            ? DB::table('report_settings')->where('id', 'default')->value('encrypted_token') : null;

        return $encrypted ? Crypt::decryptString($encrypted) : config('report_sync.token');
    }

    public function configured(): bool
    {
        return (Schema::hasTable('report_settings') && DB::table('report_settings')->where('id', 'default')->exists())
            || filled(config('report_sync.token'));
    }

    public function save(string $token): void
    {
        DB::table('report_settings')->upsert([
            ['id' => 'default', 'encrypted_token' => Crypt::encryptString(trim($token)),
                'created_at' => now(), 'updated_at' => now()],
        ], ['id'], ['encrypted_token', 'updated_at']);
    }
}
