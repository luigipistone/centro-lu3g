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
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

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
