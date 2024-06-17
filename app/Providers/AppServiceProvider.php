<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\DockerComposeService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DockerComposeService::class, function ($app) {
            return new DockerComposeService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
