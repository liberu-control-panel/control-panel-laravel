<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\DockerComposeService;

class DockerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(DockerComposeService::class, function ($app) {
            return new DockerComposeService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}