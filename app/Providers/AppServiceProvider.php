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

        // The real users/members/permission tables are hand-managed (.ai/rules/database.md)
        // and have no production migration. Tests still need them, so load a test-only
        // migration path -- never against the real database.
        if ($this->app->runningUnitTests()) {
            $this->loadMigrationsFrom(database_path('testing-migrations'));
        }
    }
}
