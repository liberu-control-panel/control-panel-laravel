<?php

declare(strict_types=1);

namespace Liberu\ControlPanel\Kubernetes\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Liberu\ControlPanel\Kubernetes\Models\Cluster;
use Liberu\ControlPanel\Kubernetes\Models\HelmRelease;
use Liberu\ControlPanel\Kubernetes\Models\KubernetesAutoscaler;
use Liberu\ControlPanel\Kubernetes\Models\KubernetesClusterView;
use Liberu\ControlPanel\Kubernetes\Models\KubernetesIngress;
use Liberu\ControlPanel\Kubernetes\Models\KubernetesNamespace;
use Liberu\ControlPanel\Kubernetes\Models\KubernetesNode;
use Liberu\ControlPanel\Kubernetes\Models\KubernetesRbacBinding;
use Liberu\ControlPanel\Kubernetes\Models\KubernetesStorageClaim;
use Liberu\ControlPanel\Kubernetes\Models\KubernetesUpgrade;
use Liberu\ControlPanel\Kubernetes\Models\KubernetesWorkload;

final class RegisterKubernetesAsset
{
    public function execute(array $a): Model
    {
        $teamId = trim((string) ($a['team_id'] ?? ''));
        if ($teamId === '') {
            throw ValidationException::withMessages(['team_id' => 'A team is required.']);
        }

        $kind = (string) ($a['kind'] ?? '');
        $map = ['node' => KubernetesNode::class, 'namespace' => KubernetesNamespace::class, 'rbac' => KubernetesRbacBinding::class, 'workload' => KubernetesWorkload::class, 'ingress' => KubernetesIngress::class, 'helm' => HelmRelease::class, 'storage' => KubernetesStorageClaim::class, 'autoscaling' => KubernetesAutoscaler::class, 'upgrade' => KubernetesUpgrade::class, 'cluster-view' => KubernetesClusterView::class];
        if (! isset($map[$kind])) {
            throw ValidationException::withMessages(['kind' => 'Unsupported Kubernetes asset.']);
        } $a['id'] = $a['id'] ?? (string) Str::uuid();
        $a['team_id'] = $teamId;
        if (isset($a['cluster_id']) && ! Cluster::query()->whereKey($a['cluster_id'])->where('team_id', $teamId)->exists()) {
            abort(404);
        }
        if ($kind === 'cluster-view' && isset($a['cluster_ids'])) {
            $clusterIds = array_values(array_filter((array) $a['cluster_ids']));
            if (count($clusterIds) !== Cluster::query()->whereIn('id', $clusterIds)->where('team_id', $teamId)->count()) {
                abort(404);
            }
        }
        if ($kind === 'workload') {
            $a['kind'] = $a['workload_kind'] ?? 'Deployment';
        } else {
            unset($a['kind']);
        }
        if ($kind === 'autoscaling') {
            $target = trim((string) ($a['target'] ?? ''));
            $min = (int) ($a['min_replicas'] ?? 0);
            $max = (int) ($a['max_replicas'] ?? 0);
            if ($target === '' || $min < 1 || $max < $min) {
                throw ValidationException::withMessages(['autoscaling' => 'An autoscaler needs a target and valid replica bounds.']);
            }
            $a['metric'] = strtolower(trim((string) ($a['metric'] ?? 'cpu')));
            if (! in_array($a['metric'], ['cpu', 'memory', 'requests-per-second'], true)) {
                throw ValidationException::withMessages(['metric' => 'Unsupported autoscaler metric.']);
            }
        }

        return $map[$kind]::query()->create($a);
    }
}
