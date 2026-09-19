<?php

namespace App\Providers;

use App\Models\CaseRecord;
use App\Policies\CaseRecordPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
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
        // Policy gates
        Gate::policy(CaseRecord::class, CaseRecordPolicy::class);
        // Rate limiting
        RateLimiter::for(
            'login',
            fn($request) =>
            Limit::perMinute(50)->by($request->ip())
        );

        RateLimiter::for(
            'case-submit',
            fn($request) =>
            Limit::perMinute(30)->by($request->ip())
        );

        RateLimiter::for(
            'pin-verify',
            fn($request) =>
            Limit::perMinute(10)->by($request->ip())
        );

        Route::bind('department_head', fn(string $id) => \App\Models\User::where('id', $id)
            ->where('role', \App\Enums\Role::DEPARTMENT_HEAD)
            ->firstOrFail());
    }
}
