<?php

namespace App\Providers;

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
        // Role/permission rows in model_has_roles now use 'App\Models\User'
        // (converted by abg_update.sql), so no morph map is needed.
    }
}
