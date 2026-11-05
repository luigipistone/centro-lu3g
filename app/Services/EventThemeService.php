<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class EventThemeService
{
    public const THEMES = ['standard', 'halloween', 'natale', 'pasqua', 'estate'];

    public function personal(string $userId): ?string
    {
        $theme = json_decode(DB::table('app_settings')->where('key', 'event_theme:user:'.$userId)->value('value') ?? 'null', true);
        return in_array($theme, self::THEMES, true) ? $theme : null;
    }

    public function forUser(?string $userId): string
    {
        if (! $userId) return 'standard';
        if (DB::table('user_roles')->where('user_id', $userId)->value('role') === 'superadmin') {
            return $this->personal($userId) ?? $this->current();
        }
        return $this->current();
    }

    public function setPersonal(string $userId, ?string $theme): void
    {
        $key = 'event_theme:user:'.$userId;
        if ($theme === null) {
            DB::table('app_settings')->where('key', $key)->delete();
            return;
        }
        DB::table('app_settings')->upsert([[
            'id' => (string) str()->uuid(), 'key' => $key,
            'value' => json_encode($theme, JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]], ['key'], ['value', 'updated_at']);
    }

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
