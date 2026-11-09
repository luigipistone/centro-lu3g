<?php

use App\Http\Controllers\IntegrationApiController;
use App\Http\Controllers\CommonApiController;
use App\Http\Middleware\AuthenticateIntegration;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['throttle:120,1', AuthenticateIntegration::class])->group(function () {
    Route::get('/me', [IntegrationApiController::class, 'me']);
    Route::get('/availability', [CommonApiController::class, 'availability']);
    Route::post('/connect/revoke', [IntegrationApiController::class, 'revoke']);
    Route::get('/clients', [IntegrationApiController::class, 'clients']);
    Route::get('/projects', [IntegrationApiController::class, 'projects']);
    Route::get('/tasks', [IntegrationApiController::class, 'tasks']);
    Route::post('/tasks', [IntegrationApiController::class, 'storeTask']);
    Route::get('/tasks/{id}', [IntegrationApiController::class, 'showTask']);
    Route::get('/tasks/{id}/subtasks', [IntegrationApiController::class, 'subtasks']);
    Route::post('/tasks/{id}/subtasks', [IntegrationApiController::class, 'storeSubtask']);
    Route::patch('/subtasks/{id}', [IntegrationApiController::class, 'updateSubtask']);
    Route::post('/subtasks/{id}/comments', [IntegrationApiController::class, 'storeComment']);
    Route::post('/webhooks', [IntegrationApiController::class, 'storeWebhook']);
    Route::get('/webhooks', [CommonApiController::class, 'webhooks']);
    Route::delete('/webhooks/{id}', [IntegrationApiController::class, 'deleteWebhook']);
    Route::get('/{resource}', [CommonApiController::class, 'index'])->where('resource', 'users|services|absences');
    Route::get('/{resource}/{id}', [CommonApiController::class, 'show'])->where('resource', 'clients|projects|users|services|absences');
});
