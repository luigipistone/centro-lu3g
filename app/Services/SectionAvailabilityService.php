<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SectionAvailabilityService
{
    public const SECTIONS = [
        'clients' => ['label' => 'Clienti', 'group' => 'Menu'],
        'projects' => ['label' => 'Progetti', 'group' => 'Menu'],
        'tasks' => ['label' => 'Task', 'group' => 'Menu'],
        'calendar' => ['label' => 'Calendario', 'group' => 'Menu'],
        'documents' => ['label' => 'Documenti', 'group' => 'Menu'],
        'passwords' => ['label' => 'Password', 'group' => 'Menu'],
        'absences' => ['label' => 'Assenze', 'group' => 'Menu'],
        'social' => ['label' => 'Social', 'group' => 'Aggiornamenti'],
        'newsletter' => ['label' => 'Newsletter', 'group' => 'Aggiornamenti'],
        'seo' => ['label' => 'SEO', 'group' => 'Aggiornamenti'],
        'adv' => ['label' => 'ADV', 'group' => 'Aggiornamenti'],
        'billing' => ['label' => 'Fatturazione', 'group' => 'Amministrazione'],
        'users' => ['label' => 'Utenti', 'group' => 'Amministrazione'],
        'modules' => ['label' => 'Moduli', 'group' => 'Amministrazione'],
        'ai_agency' => ['label' => 'Agenzia AI', 'group' => 'Amministrazione'],
    ];

    public function statuses(): array
    {
        $statuses = array_fill_keys(array_keys(self::SECTIONS), true);
        if (! Schema::hasTable('section_availability')) {
            return $statuses;
        }

        foreach (DB::table('section_availability')->pluck('enabled', 'section_key') as $key => $enabled) {
            if (array_key_exists($key, $statuses)) {
                $statuses[$key] = (bool) $enabled;
            }
        }

        return $statuses;
    }

    public function settingsRows(): array
    {
        $statuses = $this->statuses();

        return collect(self::SECTIONS)->map(fn ($section, $key) => [
            'key' => $key,
            'label' => $section['label'],
            'group' => $section['group'],
            'enabled' => $statuses[$key],
        ])->values()->all();
    }

    public function sectionForRoute(string $name): ?string
    {
        if (str_starts_with($name, 'profile.absences.') || str_starts_with($name, 'attendance.') || str_starts_with($name, 'absences.')) {
            return 'absences';
        }
        if (str_starts_with($name, 'project-templates.') || str_starts_with($name, 'projects.') || str_starts_with($name, 'figma.')) {
            return 'projects';
        }
        if (str_starts_with($name, 'document-messages.') || str_starts_with($name, 'document-groups.')) {
            return 'documents';
        }
        foreach (['social', 'newsletter', 'seo', 'adv'] as $area) {
            if ($name === "updates.{$area}" || str_starts_with($name, "updates-{$area}.")) {
                return $area;
            }
        }
        foreach (['clients', 'tasks', 'calendar', 'documents', 'passwords', 'billing', 'users', 'modules'] as $area) {
            if (str_starts_with($name, "{$area}.")) {
                return $area;
            }
        }

        return str_starts_with($name, 'ai-agency.') ? 'ai_agency' : null;
    }
}
