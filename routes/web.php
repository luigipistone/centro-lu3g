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
    Route::post('/clients', [CentroPageController::class, 'store'])->defaults('section', 'clients')->name('clients.store');
    Route::put('/clients/{id}', [CentroPageController::class, 'update'])->defaults('section', 'clients')->name('clients.update');
    Route::delete('/clients/{id}', [CentroPageController::class, 'destroy'])->defaults('section', 'clients')->name('clients.destroy');
    Route::get('/projects', [CentroPageController::class, 'index'])->defaults('section', 'projects')->name('projects.index');
    Route::post('/projects', [CentroPageController::class, 'store'])->defaults('section', 'projects')->name('projects.store');
    Route::put('/projects/{id}', [CentroPageController::class, 'update'])->defaults('section', 'projects')->name('projects.update');
    Route::delete('/projects/{id}', [CentroPageController::class, 'destroy'])->defaults('section', 'projects')->name('projects.destroy');
    Route::get('/tasks', [CentroPageController::class, 'index'])->defaults('section', 'tasks')->name('tasks.index');
    Route::post('/tasks', [CentroPageController::class, 'store'])->defaults('section', 'tasks')->name('tasks.store');
    Route::put('/tasks/{id}', [CentroPageController::class, 'update'])->defaults('section', 'tasks')->name('tasks.update');
    Route::delete('/tasks/{id}', [CentroPageController::class, 'destroy'])->defaults('section', 'tasks')->name('tasks.destroy');
    Route::get('/billing', [CentroPageController::class, 'index'])->defaults('section', 'billing')->name('billing.index');
    Route::get('/users', [CentroPageController::class, 'index'])->defaults('section', 'users')->name('users.index');
    Route::get('/settings', [CentroPageController::class, 'index'])->defaults('section', 'settings')->name('settings.index');
    Route::post('/settings/services', [CentroPageController::class, 'store'])->defaults('section', 'settings')->name('settings.store');
    Route::put('/settings/services/{id}', [CentroPageController::class, 'update'])->defaults('section', 'settings')->name('settings.update');
    Route::delete('/settings/services/{id}', [CentroPageController::class, 'destroy'])->defaults('section', 'settings')->name('settings.destroy');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
