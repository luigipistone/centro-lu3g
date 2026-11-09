<?php

use App\Http\Middleware\AuditUserActions;
use App\Http\Middleware\EnforceRolePermissions;
use App\Http\Middleware\EnforceSectionAvailability;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            AuditUserActions::class,
            EnsureActiveAccount::class,
            EnforceRolePermissions::class,
            EnforceSectionAvailability::class,
            HandleInertiaRequests::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/v1/*')) return null;
            $status = $e instanceof \Illuminate\Validation\ValidationException ? 422
                : ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $e->getStatusCode() : 500);
            $code = match ($status) { 401 => 'unauthorized', 403 => 'forbidden', 404 => 'not_found', 409 => 'conflict', 422 => 'validation_error', 429 => 'rate_limited', default => 'server_error' };
            $message = match ($status) { 401 => 'Token non valido o scaduto.', 403 => 'Operazione non consentita.', 404 => 'Risorsa non trovata.', 409 => 'Conflitto: rileggi la risorsa e verifica i dati.', 422 => 'Dati non validi.', 429 => 'Troppe richieste.', default => 'Errore interno. Riprova più tardi.' };
            return response()->json(['error' => ['code' => $code, 'message' => $message, 'fields' => $e instanceof \Illuminate\Validation\ValidationException ? $e->errors() : (object) []]], $status)
                ->withHeaders($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $e->getHeaders() : [])
                ->header('Cache-Control', 'no-store');
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || ($request->routeIs('tasks.drive.*', 'projects.drive.*', 'settings.api-*') && $request->expectsJson()),
        );
    })->create();
