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
    Route::get('/clients/{id}', [CentroPageController::class, 'show'])->defaults('section', 'clients')->name('clients.show');
    Route::put('/clients/{id}', [CentroPageController::class, 'update'])->defaults('section', 'clients')->name('clients.update');
    Route::delete('/clients/{id}', [CentroPageController::class, 'destroy'])->defaults('section', 'clients')->name('clients.destroy');
    Route::get('/projects', [CentroPageController::class, 'index'])->defaults('section', 'projects')->name('projects.index');
    Route::post('/projects', [CentroPageController::class, 'store'])->defaults('section', 'projects')->name('projects.store');
    Route::get('/projects/{id}', [CentroPageController::class, 'show'])->defaults('section', 'projects')->name('projects.show');
    Route::put('/projects/{id}', [CentroPageController::class, 'update'])->defaults('section', 'projects')->name('projects.update');
    Route::delete('/projects/{id}', [CentroPageController::class, 'destroy'])->defaults('section', 'projects')->name('projects.destroy');
    Route::get('/tasks', [CentroPageController::class, 'index'])->defaults('section', 'tasks')->name('tasks.index');
    Route::post('/tasks', [CentroPageController::class, 'store'])->defaults('section', 'tasks')->name('tasks.store');
    Route::get('/tasks/{id}', [CentroPageController::class, 'show'])->defaults('section', 'tasks')->name('tasks.show');
    Route::put('/tasks/{id}', [CentroPageController::class, 'update'])->defaults('section', 'tasks')->name('tasks.update');
    Route::post('/tasks/{id}/comments', [CentroPageController::class, 'storeTaskComment'])->name('tasks.comments.store');
    Route::patch('/tasks/{id}/status', [CentroPageController::class, 'updateTaskStatus'])->name('tasks.status.update');
    Route::delete('/tasks/{id}', [CentroPageController::class, 'destroy'])->defaults('section', 'tasks')->name('tasks.destroy');
    Route::get('/calendar', [CentroPageController::class, 'index'])->defaults('section', 'calendar')->name('calendar.index');
    Route::get('/updates/social', [CentroPageController::class, 'index'])->defaults('section', 'updates-social')->name('updates.social');
    Route::post('/updates/social', [CentroPageController::class, 'store'])->defaults('section', 'updates-social')->name('updates-social.store');
    Route::put('/updates/social/{id}', [CentroPageController::class, 'update'])->defaults('section', 'updates-social')->name('updates-social.update');
    Route::delete('/updates/social/{id}', [CentroPageController::class, 'destroy'])->defaults('section', 'updates-social')->name('updates-social.destroy');
    Route::get('/updates/newsletter', [CentroPageController::class, 'index'])->defaults('section', 'updates-newsletter')->name('updates.newsletter');
    Route::post('/updates/newsletter', [CentroPageController::class, 'store'])->defaults('section', 'updates-newsletter')->name('updates-newsletter.store');
    Route::put('/updates/newsletter/{id}', [CentroPageController::class, 'update'])->defaults('section', 'updates-newsletter')->name('updates-newsletter.update');
    Route::delete('/updates/newsletter/{id}', [CentroPageController::class, 'destroy'])->defaults('section', 'updates-newsletter')->name('updates-newsletter.destroy');
    Route::get('/updates/seo', [CentroPageController::class, 'index'])->defaults('section', 'updates-seo')->name('updates.seo');
    Route::post('/updates/seo', [CentroPageController::class, 'store'])->defaults('section', 'updates-seo')->name('updates-seo.store');
    Route::put('/updates/seo/{id}', [CentroPageController::class, 'update'])->defaults('section', 'updates-seo')->name('updates-seo.update');
    Route::delete('/updates/seo/{id}', [CentroPageController::class, 'destroy'])->defaults('section', 'updates-seo')->name('updates-seo.destroy');
    Route::get('/updates/adv', [CentroPageController::class, 'index'])->defaults('section', 'updates-adv')->name('updates.adv');
    Route::post('/updates/adv', [CentroPageController::class, 'store'])->defaults('section', 'updates-adv')->name('updates-adv.store');
    Route::put('/updates/adv/{id}', [CentroPageController::class, 'update'])->defaults('section', 'updates-adv')->name('updates-adv.update');
    Route::delete('/updates/adv/{id}', [CentroPageController::class, 'destroy'])->defaults('section', 'updates-adv')->name('updates-adv.destroy');
    Route::get('/billing', [CentroPageController::class, 'index'])->defaults('section', 'billing')->name('billing.index');
    Route::post('/billing', [CentroPageController::class, 'store'])->defaults('section', 'billing')->name('billing.store');
    Route::get('/billing/{id}', [CentroPageController::class, 'show'])->defaults('section', 'billing')->name('billing.show');
    Route::put('/billing/{id}', [CentroPageController::class, 'update'])->defaults('section', 'billing')->name('billing.update');
    Route::post('/billing/{id}/lines', [CentroPageController::class, 'storeDocumentLine'])->name('billing.lines.store');
    Route::delete('/billing/{documentId}/lines/{lineId}', [CentroPageController::class, 'destroyDocumentLine'])->name('billing.lines.destroy');
    Route::post('/billing/{id}/payments', [CentroPageController::class, 'storeDocumentPayment'])->name('billing.payments.store');
    Route::delete('/billing/{documentId}/payments/{paymentId}', [CentroPageController::class, 'destroyDocumentPayment'])->name('billing.payments.destroy');
    Route::delete('/billing/{id}', [CentroPageController::class, 'destroy'])->defaults('section', 'billing')->name('billing.destroy');
    Route::get('/users', [CentroPageController::class, 'index'])->defaults('section', 'users')->name('users.index');
    Route::post('/users', [CentroPageController::class, 'store'])->defaults('section', 'users')->name('users.store');
    Route::put('/users/{id}', [CentroPageController::class, 'update'])->defaults('section', 'users')->name('users.update');
    Route::delete('/users/{id}', [CentroPageController::class, 'destroy'])->defaults('section', 'users')->name('users.destroy');
    Route::get('/settings', [CentroPageController::class, 'index'])->defaults('section', 'settings')->name('settings.index');
    Route::post('/settings/services', [CentroPageController::class, 'store'])->defaults('section', 'settings')->name('settings.store');
    Route::put('/settings/services/{id}', [CentroPageController::class, 'update'])->defaults('section', 'settings')->name('settings.update');
    Route::delete('/settings/services/{id}', [CentroPageController::class, 'destroy'])->defaults('section', 'settings')->name('settings.destroy');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
