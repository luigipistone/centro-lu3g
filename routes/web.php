<?php

use App\Http\Controllers\CentroPageController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', [CentroPageController::class, 'dashboard'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/clients', [CentroPageController::class, 'index'])->defaults('section', 'clients')->name('clients.index');
    Route::get('/projects', [CentroPageController::class, 'index'])->defaults('section', 'projects')->name('projects.index');
    Route::get('/tasks', [CentroPageController::class, 'index'])->defaults('section', 'tasks')->name('tasks.index');
    Route::get('/billing', [CentroPageController::class, 'index'])->defaults('section', 'billing')->name('billing.index');
    Route::get('/users', [CentroPageController::class, 'index'])->defaults('section', 'users')->name('users.index');
    Route::get('/settings', [CentroPageController::class, 'index'])->defaults('section', 'settings')->name('settings.index');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
