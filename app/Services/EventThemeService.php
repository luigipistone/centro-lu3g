<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class EventThemeService
{
    public const THEMES = ['standard', 'halloween', 'natale', 'pasqua', 'estate'];

    public function current(): string
    {
        $theme = json_decode(DB::table('app_settings')->where('key', 'event_theme')->value('value') ?? '"standard"', true);

        return in_array($theme, self::THEMES, true) ? $theme : 'standard';
    }

    public function set(string $theme): void
    {
        DB::table('app_settings')->upsert([[
            'id' => (string) str()->uuid(),
            'key' => 'event_theme',
            'value' => json_encode($theme, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['key'], ['value', 'updated_at']);
    }
}
