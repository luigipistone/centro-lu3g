<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\CalendarRealtime::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        $realtime = $this->app->make(\App\Services\CalendarRealtime::class);
        \Illuminate\Support\Facades\DB::listen($realtime->observe(...));
        $this->app->terminating($realtime->flush(...));
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Queue\Events\JobProcessed::class, fn () => $realtime->flush(),
        );
    }
}
