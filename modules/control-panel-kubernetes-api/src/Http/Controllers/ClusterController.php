<?php

declare(strict_types=1);

namespace Liberu\ControlPanel\KubernetesApi\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Liberu\ControlPanel\Kubernetes\Actions\ArchiveCluster;
use Liberu\ControlPanel\Kubernetes\Actions\ReconcileWorkload;
use Liberu\ControlPanel\Kubernetes\Actions\RecordKubernetesResource;
use Liberu\ControlPanel\Kubernetes\Actions\RegisterCluster;
use Liberu\ControlPanel\Kubernetes\Actions\RegisterKubernetesAsset;
use Liberu\ControlPanel\Kubernetes\Actions\RollbackHelmRelease;
use Liberu\ControlPanel\Kubernetes\Actions\SuspendCluster;
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
use Liberu\ControlPanel\Kubernetes\Queries\ListClusters;

final class ClusterController
{
    /** @var array<string, class-string<Model>> */
    private const ASSET_MODELS = [
        'node' => KubernetesNode::class,
        'namespace' => KubernetesNamespace::class,
        'rbac' => KubernetesRbacBinding::class,
        'workload' => KubernetesWorkload::class,
        'ingress' => KubernetesIngress::class,
        'helm' => HelmRelease::class,
        'storage' => KubernetesStorageClaim::class,
        'autoscaling' => KubernetesAutoscaler::class,
        'upgrade' => KubernetesUpgrade::class,
        'cluster-view' => KubernetesClusterView::class,
    ];

    /** @var array<string, list<string>> */
    private const ASSET_FIELDS = [
        'node' => ['cluster_id', 'name', 'uid', 'kubernetes_version', 'container_runtime', 'os_image', 'kernel_version', 'architecture', 'status', 'status_message', 'schedulable', 'labels', 'taints', 'addresses', 'capacity', 'allocatable', 'conditions', 'last_heartbeat_at'],
        'namespace' => ['cluster_id', 'name', 'status', 'labels', 'quotas'],
        'rbac' => ['cluster_id', 'namespace', 'name', 'role', 'subjects', 'rules', 'active'],
        'workload' => ['cluster_id', 'namespace', 'name', 'kind', 'image', 'replicas', 'status', 'spec'],
        'ingress' => ['cluster_id', 'namespace', 'name', 'host', 'paths', 'tls', 'backend', 'status'],
        'helm' => ['cluster_id', 'namespace', 'name', 'chart', 'version', 'values', 'status'],
        'storage' => ['cluster_id', 'namespace', 'name', 'storage_class', 'capacity_bytes', 'access_modes', 'status'],
        'autoscaling' => ['cluster_id', 'namespace', 'name', 'target', 'min_replicas', 'max_replicas', 'metric', 'status'],
        'upgrade' => ['cluster_id', 'from_version', 'to_version', 'status', 'started_at', 'completed_at', 'details'],
        'cluster-view' => ['name', 'cluster_ids', 'filters', 'status'],
    ];

    public function index(Request $request, ListClusters $list): JsonResponse
    {
        $teamId = $request->user()?->current_team_id;
        abort_if($teamId === null, 403, 'A current team is required.');
        $items = $list->execute($teamId, $request->integer('per_page', 25));

        return response()->json(['data' => $items->through(static fn (Cluster $item): array => self::resource($item)), 'meta' => ['current_page' => $items->currentPage(), 'per_page' => $items->perPage(), 'total' => $items->total()]]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $teamId = $request->user()?->current_team_id;
        abort_if($teamId === null, 403, 'A current team is required.');
        $item = Cluster::query()->whereKey($id)->where('team_id', $teamId)->firstOrFail();

        return response()->json(['data' => self::resource($item)]);
    }

    public function assets(Request $request): JsonResponse
    {
        $teamId = $request->user()?->current_team_id;
        abort_if($teamId === null, 403, 'A current team is required.');

        $data = $request->validate([
            'kind' => ['required', 'string', 'in:'.implode(',', array_keys(self::ASSET_MODELS))],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $model = self::ASSET_MODELS[$data['kind']];
        $items = $model::query()
            ->where('team_id', $teamId)
            ->latest()
            ->paginate($data['per_page'] ?? 25)
            ->withQueryString();

        return response()->json([
            'data' => $items->getCollection()->map(fn ($item): array => [
                'id' => $item->getKey(),
                'type' => 'control-panel-kubernetes-'.$data['kind'],
                'attributes' => $item->only(self::ASSET_FIELDS[$data['kind']]),
            ])->values(),
            'meta' => [
                'kind' => $data['kind'],
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
            'links' => [
                'first' => $items->url(1),
                'last' => $items->url($items->lastPage()),
                'prev' => $items->previousPageUrl(),
                'next' => $items->nextPageUrl(),
            ],
        ]);
    }

    public function store(Request $request, RegisterCluster $register): JsonResponse
    {
        $teamId = $request->user()?->current_team_id;
        abort_if($teamId === null, 403, 'A current team is required.');
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'endpoint' => ['required', 'url', 'max:255']]);
        $item = $register->execute(array_merge($data, ['team_id' => $teamId]));

        return response()->json(['data' => self::resource($item)], 201);
    }

    public function suspend(Request $request, Cluster $cluster, SuspendCluster $suspend): JsonResponse
    {
        $this->assertTeam($request, $cluster);

        return response()->json(['data' => self::resource($suspend->execute($cluster))]);
    }

    public function archive(Request $request, Cluster $cluster, ArchiveCluster $archive): JsonResponse
    {
        $this->assertTeam($request, $cluster);

        return response()->json(['data' => self::resource($archive->execute($cluster))]);
    }

    public function resourceRecord(Request $request, RecordKubernetesResource $record): JsonResponse
    {
        $teamId = $request->user()?->current_team_id;
        abort_if($teamId === null, 403, 'A current team is required.');
        $data = $request->validate(['cluster_id' => ['nullable', 'uuid'], 'kind' => ['required', 'in:node,namespace,rbac,workload,ingress,helm,storage,autoscaling,upgrade,cluster-view'], 'name' => ['required', 'string', 'max:255'], 'namespace' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'string', 'max:50'], 'spec' => ['nullable', 'array']]);
        $item = $record->execute(array_merge($data, ['team_id' => $teamId]));

        return response()->json(['data' => ['id' => $item->getKey(), 'type' => 'control-panel-kubernetes-resource', 'attributes' => $item->only(['cluster_id', 'kind', 'name', 'namespace', 'status', 'spec'])]], 201);
    }

    public function asset(Request $request, RegisterKubernetesAsset $register): JsonResponse
    {
        $teamId = $request->user()?->current_team_id;
        abort_if($teamId === null, 403, 'A current team is required.');
        $data = $request->validate(['kind' => ['required', 'in:node,namespace,rbac,workload,ingress,helm,storage,autoscaling,upgrade,cluster-view'], 'payload' => ['required', 'array']]);
        $item = $register->execute(array_merge($data['payload'], ['kind' => $data['kind'], 'team_id' => $teamId]));

        return response()->json(['data' => ['id' => $item->getKey(), 'type' => 'control-panel-kubernetes-'.$data['kind'], 'attributes' => $item->only(self::ASSET_FIELDS[$data['kind']])]], 201);
    }

    public function reconcileWorkload(Request $request, KubernetesWorkload $workload, ReconcileWorkload $reconcile): JsonResponse
    {
        abort_if($request->user()?->current_team_id === null, 403, 'A current team is required.');
        abort_unless((string) $workload->team_id === (string) $request->user()?->current_team_id, 404);
        $item = $reconcile->execute($workload);

        return response()->json(['data' => ['id' => $item->getKey(), 'type' => 'control-panel-kubernetes-workload', 'attributes' => $item->only(self::ASSET_FIELDS['workload'])]]);
    }

    public function rollbackHelmRelease(Request $request, HelmRelease $release, RollbackHelmRelease $rollback): JsonResponse
    {
        abort_if($request->user()?->current_team_id === null, 403, 'A current team is required.');
        abort_unless((string) $release->team_id === (string) $request->user()?->current_team_id, 404);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        $item = $rollback->execute($release, $data['revision']);

        return response()->json(['data' => ['id' => $item->getKey(), 'type' => 'control-panel-kubernetes-helm', 'attributes' => $item->only(self::ASSET_FIELDS['helm'])]]);
    }

    private static function resource(Cluster $item): array
    {
        return ['id' => $item->getKey(), 'type' => 'control-panel-kubernetes-cluster', 'attributes' => $item->only(['name', 'endpoint', 'status'])];
    }

    private function assertTeam(Request $request, Cluster $cluster): void
    {
        abort_if($request->user()?->current_team_id === null, 403, 'A current team is required.');
        abort_unless((string) $cluster->team_id === (string) $request->user()?->current_team_id, 404);
    }
}
