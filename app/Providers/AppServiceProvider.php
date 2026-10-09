<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        RateLimiter::for('login', function (Request $request) {
            $email = $request->input('email', '');

            return [
                Limit::perMinute(5)->by($request->ip().'|'.(is_string($email) ? mb_strtolower($email) : 'invalid')),
                Limit::perMinute(40)->by($request->ip()),
            ];
        });
    }
}
