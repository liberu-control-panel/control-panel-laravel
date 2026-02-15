<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\KubernetesService;
use App\Services\KubernetesSecurityService;
use App\Services\ContainerManagerService;

class KubernetesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(KubernetesService::class, function ($app) {
            return new KubernetesService();
        });

        $this->app->singleton(KubernetesSecurityService::class, function ($app) {
            return new KubernetesSecurityService();
        });

        $this->app->singleton(ContainerManagerService::class, function ($app) {
            return new ContainerManagerService(
                $app->make(\App\Services\DockerComposeService::class),
                $app->make(KubernetesService::class)
            );
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
