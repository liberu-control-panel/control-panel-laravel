<?php

declare(strict_types=1);

namespace Liberu\ControlPanel\Kubernetes;

use Illuminate\Support\ServiceProvider;
use Liberu\ControlPanel\Kubernetes\Actions\DeleteHelmRelease;
use Liberu\ControlPanel\Kubernetes\Actions\ReconcileWorkload;
use Liberu\ControlPanel\Kubernetes\Actions\RecordHelmRevision;
use Liberu\ControlPanel\Kubernetes\Actions\RecordKubernetesResource;
use Liberu\ControlPanel\Kubernetes\Actions\RegisterCluster;
use Liberu\ControlPanel\Kubernetes\Actions\RegisterKubernetesAsset;
use Liberu\ControlPanel\Kubernetes\Actions\RollbackHelmRelease;
use Liberu\ControlPanel\Kubernetes\Actions\UpdateHelmRelease;
use Liberu\ControlPanel\Kubernetes\Contracts\WorkloadReconciler;
use Liberu\ControlPanel\Kubernetes\Queries\ListClusters;
use Liberu\ControlPanel\Kubernetes\Services\UnavailableWorkloadReconciler;

final class KubernetesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(WorkloadReconciler::class, UnavailableWorkloadReconciler::class);
        $this->app->scoped(ReconcileWorkload::class);
        $this->app->scoped(RecordHelmRevision::class);
        $this->app->scoped(RollbackHelmRelease::class);
        $this->app->scoped(DeleteHelmRelease::class);
        $this->app->scoped(RegisterCluster::class);
        $this->app->scoped(ListClusters::class);
        $this->app->scoped(RecordKubernetesResource::class);
        $this->app->scoped(RegisterKubernetesAsset::class);
        $this->app->scoped(UpdateHelmRelease::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
