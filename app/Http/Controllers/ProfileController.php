<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\CentroNotificationService;
use App\Services\AccountArchiveService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $profile = DB::table('profiles as p')
            ->leftJoin('users as manager', 'manager.id', '=', 'p.manager_user_id')
            ->where('p.user_id', $request->user()->id)
            ->select('p.*', 'manager.name as manager_name')
            ->first();
        if ($profile) {
            $profile->smartworking_days = json_decode($profile->smartworking_days ?: '[]', true) ?: [];
            $profile->work_schedule = json_decode($profile->work_schedule ?: '[]', true) ?: [];
        }

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'profile' => $profile,
            'notificationPreferences' => $this->notificationPreferenceRows($request->user()->id),
            'absences' => DB::table('absence_requests')
                ->where('user_id', $request->user()->id)
                ->latest('start_date')
                ->limit(30)
                ->get(),
            'archiveRequest' => DB::table('account_archive_requests')->where('user_id', $request->user()->id)->latest()->first(),
            'dossierDocuments' => $this->dossierDocuments($request->user()->id),
            'employeeDossier' => $this->employeeDossier($request->user()->id),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $fullName = trim(($payload['first_name'] ?? '').' '.($payload['last_name'] ?? '')) ?: $payload['name'];
        $nameParts = preg_split('/\s+/', $fullName, 2);
        $request->user()->fill([
            'name' => $fullName,
            'email' => $payload['email'],
        ]);

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        $existingProfile = DB::table('profiles')->where('user_id', $request->user()->id)->first(['id', 'created_at']);
        DB::table('profiles')->updateOrInsert(
            ['user_id' => $request->user()->id],
            [
                'id' => $existingProfile->id ?? (string) str()->uuid(),
                'full_name' => $fullName,
                'first_name' => $payload['first_name'] ?? ($nameParts[0] ?? null),
                'last_name' => $payload['last_name'] ?? ($nameParts[1] ?? null),
                'phone' => ($payload['phone'] ?? null) ?: null,
                'completion_effect' => $payload['completion_effect'] ?? 'balloons',
                'updated_at' => now(),
                'created_at' => $existingProfile->created_at ?? now(),
            ],
        );

        foreach (($payload['notification_preferences'] ?? []) as $preference) {
            $existingPreference = DB::table('notification_preferences')
                ->where('user_id', $request->user()->id)
                ->where('category', $preference['category'])
                ->first(['id', 'created_at']);

            DB::table('notification_preferences')->updateOrInsert(
                [
                    'user_id' => $request->user()->id,
                    'category' => $preference['category'],
                ],
                [
                    'id' => $existingPreference->id ?? (string) str()->uuid(),
                    'in_app' => (bool) ($preference['in_app'] ?? false),
                    'browser' => (bool) ($preference['browser'] ?? false),
                    'mail' => (bool) ($preference['mail'] ?? false),
                    'updated_at' => now(),
                    'created_at' => $existingPreference->created_at ?? now(),
                ],
            );
        }

        return Redirect::route('profile.edit');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $currentAvatar = DB::table('profiles')->where('user_id', $user->id)->value('avatar_url');
        $path = $request->file('avatar')->store('avatars', 'local');
        Storage::disk('local')->setVisibility($path, 'private');

        if ($currentAvatar && str_starts_with($currentAvatar, '/avatars/')) {
            Storage::disk('local')->delete('avatars/'.basename($currentAvatar));
            Storage::disk('public')->delete('avatars/'.basename($currentAvatar));
        }

        DB::table('profiles')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'id' => (string) str()->uuid(),
                'full_name' => $user->name,
                'avatar_url' => '/avatars/'.basename($path),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return Redirect::route('profile.edit')->with('status', 'Foto profilo aggiornata.');
    }

    public function storeAbsence(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'type' => ['required', Rule::in(['vacation', 'permission', 'sickness', 'late', 'other'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'regex:/^([01][0-9]|2[0-3]):00$/'],
            'end_time' => ['nullable', 'regex:/^([01][0-9]|2[0-3]):00$/'],
            'inps_code' => ['nullable', 'required_if:type,sickness', 'string', 'max:255'],
            'medical_document' => ['nullable', 'required_if:type,sickness', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
            'notes' => ['nullable', 'string', 'max:6000'],
        ]);
        if (in_array($payload['type'], ['vacation', 'sickness'], true)) {
            $payload['start_time'] = null;
            $payload['end_time'] = null;
        }
        if (in_array($payload['type'], ['permission', 'late'], true)) {
            $payload['end_date'] = $payload['start_date'];
        }
        if ($payload['type'] !== 'sickness') {
            $payload['inps_code'] = null;
        }
        $medicalDocumentPath = null;
        $medicalDocumentName = null;
        $medicalDocumentMime = null;
        if ($payload['type'] === 'sickness' && $request->hasFile('medical_document')) {
            $file = $request->file('medical_document');
            $medicalDocumentPath = $file->store('absence-medical-documents', 'local');
            Storage::disk('local')->setVisibility($medicalDocumentPath, 'private');
            $medicalDocumentName = $file->getClientOriginalName();
            $medicalDocumentMime = $file->getMimeType();
        }

        $absenceId = (string) str()->uuid();

        DB::table('absence_requests')->insert([
            'id' => $absenceId,
            'user_id' => $request->user()->id,
            'type' => $payload['type'],
            'start_date' => $payload['start_date'],
            'end_date' => ($payload['end_date'] ?? null) ?: $payload['start_date'],
            'start_time' => ($payload['start_time'] ?? null) ?: null,
            'end_time' => ($payload['end_time'] ?? null) ?: null,
            'inps_code' => ($payload['inps_code'] ?? null) ?: null,
            'medical_document_path' => $medicalDocumentPath,
            'medical_document_name' => $medicalDocumentName,
            'medical_document_mime' => $medicalDocumentMime,
            'status' => 'pending',
            'notes' => ($payload['notes'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->notifyAbsencePeople(
            $request->user()->id,
            $request->user()->id,
            'absence_created',
            $request->user()->name.' ha inviato una richiesta assenza.',
        );

        return Redirect::route('profile.edit')->with('status', 'Richiesta inviata.');
    }

    public function destroyAbsence(Request $request, string $id): RedirectResponse
    {
        $absence = DB::table('absence_requests')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->first();

        DB::table('absence_requests')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->delete();

        if ($absence) {
            if ($absence->medical_document_path) {
                Storage::disk('local')->delete($absence->medical_document_path);
            }

            $this->notifyAbsencePeople(
                $request->user()->id,
                $request->user()->id,
                'absence_deleted',
                $request->user()->name.' ha annullato una richiesta assenza.',
            );
        }

        return Redirect::route('profile.edit')->with('status', 'Richiesta annullata.');
    }

    public function avatar(string $filename)
    {
        abort_if($filename !== basename($filename), 404);

        $relativePath = 'avatars/'.$filename;

        if (! Storage::disk('local')->exists($relativePath) && Storage::disk('public')->exists($relativePath)) {
            Storage::disk('local')->put($relativePath, Storage::disk('public')->get($relativePath));
            Storage::disk('local')->setVisibility($relativePath, 'private');
            Storage::disk('public')->delete($relativePath);
        }

        abort_unless(Storage::disk('local')->exists($relativePath), 404);

        return response()->file(Storage::disk('local')->path($relativePath));
    }

    private function notifyAbsencePeople(string $requestUserId, ?string $actorId, string $type, string $message): void
    {
        $userIds = DB::table('user_roles')
            ->whereIn('role', ['superadmin', 'admin'])
            ->pluck('user_id')
            ->push($requestUserId)
            ->filter()
            ->unique()
            ->values();

        app(CentroNotificationService::class)->notifyUsers($userIds, $actorId, $type, $message);
    }

    private function notificationPreferenceRows(string $userId): array
    {
        $labels = [
            'tasks' => 'Task',
            'projects' => 'Progetti',
            'absences' => 'Assenze',
            'documents' => 'Documenti',
            'system' => 'Sistema',
        ];

        $rows = DB::table('notification_preferences')
            ->where('user_id', $userId)
            ->get()
            ->keyBy('category');

        return collect(CentroNotificationService::CATEGORIES)
            ->map(fn (string $category) => [
                'category' => $category,
                'label' => $labels[$category] ?? ucfirst($category),
                'in_app' => (bool) ($rows[$category]->in_app ?? true),
                'browser' => (bool) ($rows[$category]->browser ?? true),
                'mail' => (bool) ($rows[$category]->mail ?? false),
            ])
            ->values()
            ->all();
    }

    private function dossierDocuments(string $userId): array
    {
        $groupIds = DB::table('document_group_user')->where('user_id', $userId)->pluck('document_group_id');
        $ids = DB::table('company_documents')->where('audience', 'all')->pluck('id')
            ->merge(DB::table('company_document_user')->where('user_id', $userId)->pluck('company_document_id'))
            ->merge(DB::table('company_document_group')->whereIn('document_group_id', $groupIds)->pluck('company_document_id'))
            ->unique()->values();

        return DB::table('company_documents as d')
            ->leftJoin('company_document_reads as r', function ($join) use ($userId) {
                $join->on('r.company_document_id', '=', 'd.id')->where('r.user_id', '=', $userId);
            })
            ->whereIn('d.id', $ids)
            ->orderByDesc('d.created_at')
            ->select('d.id', 'd.title', 'd.category', 'd.created_at', 'r.read_at')
            ->get()->map(fn ($row) => (array) $row)->all();
    }

    private function employeeDossier(string $userId): array
    {
        if (! Schema::hasTable('employee_dossier_items')) return ['items' => [], 'summary' => []];

        $labels = [
            'identity_document' => ['Documento d’identità', true],
            'tax_identifier' => ['Codice fiscale o identificativo', true],
            'employment_contract' => ['Contratto di lavoro', true],
            'contract_change' => ['Variazione contrattuale', false],
            'medical_exam' => ['Visita medica e idoneità', true],
            'safety_course' => ['Corso o attestato di sicurezza', true],
            'equipment' => ['Dotazione aziendale', false],
            'policy' => ['Policy o documento firmato', false],
        ];
        $rows = DB::table('employee_dossier_items')->where('user_id', $userId)->where('classification', '!=', 'admin')->orderByDesc('created_at')->get();
        $status = function ($item): string {
            if ($item->replaced_at) return 'replaced';
            if (! $item->expires_at) return 'valid';
            $expiry = Carbon::parse($item->expires_at)->startOfDay();
            if ($expiry->isPast()) return 'expired';
            return $expiry->lte(now('Europe/Rome')->addDays(30)->endOfDay()) ? 'expiring' : 'valid';
        };
        $items = $rows->map(function ($item) use ($labels, $status) {
            $item->type_label = $labels[$item->type][0] ?? $item->type;
            $item->status = $status($item);
            if ($item->classification === 'medical') {
                $item->identifier = null;
                $item->notes = null;
            }
            return $item;
        })->values();
        $active = $rows->whereNull('replaced_at')->groupBy('type');
        $summary = collect($labels)->filter(fn ($definition) => $definition[1])->map(function ($definition, $type) use ($active, $status) {
            $item = $active->get($type)?->first();
            return ['type' => $type, 'label' => $definition[0], 'status' => $item ? $status($item) : 'missing'];
        })->values();

        return ['items' => $items, 'summary' => $summary];
    }

    public function requestArchive(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $user = $request->user();
        app(AccountArchiveService::class)->create($user, $user, $request->string('reason')->toString());
        $superadmins = DB::table('user_roles')->where('role', 'superadmin')->where('user_id', '!=', $user->id)->pluck('user_id');
        app(CentroNotificationService::class)->notifyUsers($superadmins, $user->id, 'account_archive_requested', $user->name.' ha richiesto l’archiviazione del proprio account.');

        return back()->with('status', 'Richiesta inviata all’amministrazione.');
    }
}
