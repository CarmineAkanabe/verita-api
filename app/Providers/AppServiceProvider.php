<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Rate limiting
        RateLimiter::for(
            'login',
            fn($request) =>
            Limit::perMinute(5)->by($request->ip())
        );

        RateLimiter::for(
            'case-submit',
            fn($request) =>
            Limit::perMinute(3)->by($request->ip())
        );

        RateLimiter::for(
            'pin-verify',
            fn($request) =>
            Limit::perMinute(10)->by($request->ip())
        );
    }
}
