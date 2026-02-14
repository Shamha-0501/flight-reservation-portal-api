<?php

namespace App\Providers;

use App\Services\EnvironmentStore;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EnvironmentStore::class, fn () => new EnvironmentStore());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Avoid "Specified key was too long" on older MySQL/MariaDB with utf8mb4.
        Schema::defaultStringLength(191);
    }
}
