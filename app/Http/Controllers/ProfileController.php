<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\CentroNotificationService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
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
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'profile' => DB::table('profiles')->where('user_id', $request->user()->id)->first(['completion_effect', 'smartworking_day']),
            'notificationPreferences' => $this->notificationPreferenceRows($request->user()->id),
            'absences' => DB::table('absence_requests')
                ->where('user_id', $request->user()->id)
                ->latest('start_date')
                ->limit(30)
                ->get(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $request->user()->fill([
            'name' => $payload['name'],
            'email' => $payload['email'],
        ]);

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        DB::table('profiles')->updateOrInsert(
            ['user_id' => $request->user()->id],
            [
                'id' => (string) str()->uuid(),
                'full_name' => $request->user()->name,
                'completion_effect' => $payload['completion_effect'] ?? 'balloons',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        foreach (($payload['notification_preferences'] ?? []) as $preference) {
            DB::table('notification_preferences')->updateOrInsert(
                [
                    'user_id' => $request->user()->id,
                    'category' => $preference['category'],
                ],
                [
                    'id' => DB::table('notification_preferences')
                        ->where('user_id', $request->user()->id)
                        ->where('category', $preference['category'])
                        ->value('id') ?: (string) str()->uuid(),
                    'in_app' => (bool) ($preference['in_app'] ?? false),
                    'browser' => (bool) ($preference['browser'] ?? false),
                    'mail' => (bool) ($preference['mail'] ?? false),
                    'updated_at' => now(),
                    'created_at' => now(),
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

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
