<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
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

        return Redirect::route('profile.edit');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $currentAvatar = DB::table('profiles')->where('user_id', $user->id)->value('avatar_url');
        $path = $request->file('avatar')->store('avatars', 'public');

        if ($currentAvatar && str_starts_with($currentAvatar, '/avatars/')) {
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
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::table('absence_requests')->insert([
            'id' => (string) str()->uuid(),
            'user_id' => $request->user()->id,
            'type' => $payload['type'],
            'start_date' => $payload['start_date'],
            'end_date' => ($payload['end_date'] ?? null) ?: $payload['start_date'],
            'start_time' => ($payload['start_time'] ?? null) ?: null,
            'end_time' => ($payload['end_time'] ?? null) ?: null,
            'status' => 'pending',
            'notes' => ($payload['notes'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Redirect::route('profile.edit')->with('status', 'Richiesta inviata.');
    }

    public function destroyAbsence(Request $request, string $id): RedirectResponse
    {
        DB::table('absence_requests')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->delete();

        return Redirect::route('profile.edit')->with('status', 'Richiesta annullata.');
    }

    public function avatar(string $filename)
    {
        abort_if($filename !== basename($filename), 404);

        $path = storage_path('app/public/avatars/'.$filename);
        abort_unless(is_file($path), 404);

        return response()->file($path);
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
