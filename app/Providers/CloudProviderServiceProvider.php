<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\DeploymentDetectionService;
use App\Services\CloudProviderManager;
use App\Services\CloudProvider\AzureAksProvider;
use App\Services\CloudProvider\AwsEksProvider;
use App\Services\CloudProvider\GoogleGkeProvider;
use App\Services\CloudProvider\DigitalOceanProvider;
use App\Services\CloudProvider\OvhProvider;
use App\Services\KubernetesService;
use App\Services\SshConnectionService;

class CloudProviderServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register deployment detection service
        $this->app->singleton(DeploymentDetectionService::class, function ($app) {
            return new DeploymentDetectionService();
        });

        // Register cloud provider manager
        $this->app->singleton(CloudProviderManager::class, function ($app) {
            $manager = new CloudProviderManager(
                $app->make(DeploymentDetectionService::class)
            );

            // Register all cloud providers
            $kubernetesService = $app->make(KubernetesService::class);
            $sshService = $app->make(SshConnectionService::class);

            $manager->registerProvider('azure', new AzureAksProvider($kubernetesService, $sshService));
            $manager->registerProvider('aws', new AwsEksProvider($kubernetesService, $sshService));
            $manager->registerProvider('gcp', new GoogleGkeProvider($kubernetesService, $sshService));
            $manager->registerProvider('digitalocean', new DigitalOceanProvider($kubernetesService, $sshService));
            $manager->registerProvider('ovh', new OvhProvider($kubernetesService, $sshService));

            return $manager;
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
