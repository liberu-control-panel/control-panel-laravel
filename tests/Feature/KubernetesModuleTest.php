<?php

declare(strict_types=1);
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Liberu\ControlPanel\Kubernetes\Actions\CordonNode;
use Liberu\ControlPanel\Kubernetes\Actions\DeleteHelmRelease;
use Liberu\ControlPanel\Kubernetes\Actions\DrainNode;
use Liberu\ControlPanel\Kubernetes\Actions\LabelNode;
use Liberu\ControlPanel\Kubernetes\Actions\RegisterCluster;
use Liberu\ControlPanel\Kubernetes\Actions\RegisterKubernetesAsset;
use Liberu\ControlPanel\Kubernetes\Actions\UncordonNode;
use Liberu\ControlPanel\Kubernetes\Actions\UnlabelNode;
use Liberu\ControlPanel\Kubernetes\Actions\UpdateHelmRelease;
use Liberu\ControlPanel\Kubernetes\KubernetesServiceProvider;
use Liberu\ControlPanel\Kubernetes\Models\HelmRelease;
use Liberu\ControlPanel\Kubernetes\Models\KubernetesNode;
use Liberu\ControlPanel\KubernetesApi\KubernetesApiServiceProvider;
use Liberu\ControlPanel\KubernetesFilament\Widgets\HelmStatsWidget;
use Liberu\Foundation\Organizations\Models\Team;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    app()->register(KubernetesServiceProvider::class);
    app()->register(KubernetesApiServiceProvider::class);
    $this->artisan('migrate');
});
it('supports cluster resources across the Kubernetes lifecycle', function (): void {
    $a = app(RegisterKubernetesAsset::class);
    $cluster = app(RegisterCluster::class)->execute(['team_id' => 'team-1', 'name' => 'Primary', 'endpoint' => 'https://k8s.example.test']);
    $node = $a->execute(['team_id' => 'team-1', 'kind' => 'node', 'cluster_id' => $cluster->getKey(), 'name' => 'worker-1', 'status' => 'Ready', 'schedulable' => true]);
    $namespace = $a->execute(['team_id' => 'team-1', 'kind' => 'namespace', 'cluster_id' => $cluster->getKey(), 'name' => 'production']);
    $rbac = $a->execute(['team_id' => 'team-1', 'kind' => 'rbac', 'name' => 'deployer', 'role' => 'admin', 'subjects' => ['account-1']]);
    $workload = $a->execute(['team_id' => 'team-1', 'kind' => 'workload', 'name' => 'web', 'workload_kind' => 'Deployment', 'image' => 'nginx']);
    $ingress = $a->execute(['team_id' => 'team-1', 'kind' => 'ingress', 'name' => 'web', 'host' => 'example.test', 'paths' => ['/']]);
    $helm = $a->execute(['team_id' => 'team-1', 'kind' => 'helm', 'namespace' => 'production', 'name' => 'app', 'chart' => 'app-chart']);
    $storage = $a->execute(['team_id' => 'team-1', 'kind' => 'storage', 'name' => 'data', 'access_modes' => ['ReadWriteOnce']]);
    $autoscaler = $a->execute(['team_id' => 'team-1', 'kind' => 'autoscaling', 'name' => 'web', 'target' => 'deployment/web', 'min_replicas' => 1, 'max_replicas' => 5, 'metric' => 'cpu']);
    $upgrade = $a->execute(['team_id' => 'team-1', 'kind' => 'upgrade', 'from_version' => '1.28', 'to_version' => '1.29']);
    $view = $a->execute(['team_id' => 'team-1', 'kind' => 'cluster-view', 'name' => 'all', 'cluster_ids' => [$cluster->getKey()]]);
    expect($node->isSchedulable())->toBeTrue()->and($namespace->name)->toBe('production')->and($rbac->role)->toBe('admin')->and($workload->image)->toBe('nginx')->and($ingress->host)->toBe('example.test')->and($helm->chart)->toBe('app-chart')->and($storage->access_modes)->toContain('ReadWriteOnce')->and($autoscaler->max_replicas)->toBe(5)->and($upgrade->to_version)->toBe('1.29')->and($view->cluster_ids)->toContain($cluster->getKey());
});
it('rejects unknown Kubernetes assets', function (): void {
    expect(fn () => app(RegisterKubernetesAsset::class)->execute(['team_id' => 'team-1', 'kind' => 'unknown']))->toThrow(ValidationException::class);
});

it('validates autoscaler targets, bounds, and metrics', function (): void {
    $action = app(RegisterKubernetesAsset::class);
    expect(fn () => $action->execute(['team_id' => 'team-1', 'kind' => 'autoscaling', 'name' => 'web', 'target' => 'deployment/web', 'min_replicas' => 5, 'max_replicas' => 2]))->toThrow(ValidationException::class);
    expect(fn () => $action->execute(['team_id' => 'team-1', 'kind' => 'autoscaling', 'name' => 'web', 'target' => 'deployment/web', 'min_replicas' => 1, 'max_replicas' => 2, 'metric' => 'latency']))->toThrow(ValidationException::class);
    $autoscaler = $action->execute(['team_id' => 'team-1', 'kind' => 'autoscaling', 'name' => 'web', 'target' => 'deployment/web', 'min_replicas' => 1, 'max_replicas' => 2, 'metric' => 'MEMORY']);
    expect($autoscaler->metric)->toBe('memory');
});

it('lists Kubernetes assets for the current team', function (): void {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $user = User::factory()->create(['current_team_id' => $team->getKey()]);
    $action = app(RegisterKubernetesAsset::class);

    $owned = $action->execute(['team_id' => $team->getKey(), 'kind' => 'namespace', 'name' => 'owned']);
    $action->execute(['team_id' => $otherTeam->getKey(), 'kind' => 'namespace', 'name' => 'foreign']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/control-panel/kubernetes/assets?kind=namespace')
        ->assertOk()
        ->assertJsonPath('meta.kind', 'namespace')
        ->assertJsonPath('data.0.attributes.name', 'owned')
        ->assertJsonMissing(['name' => 'foreign']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/control-panel/kubernetes/assets?kind=not-a-kind')
        ->assertUnprocessable();
});

it('supports provider-neutral node cordon, uncordon, and drain state transitions', function (): void {
    $node = app(RegisterKubernetesAsset::class)->execute([
        'team_id' => 'team-1',
        'kind' => 'node',
        'name' => 'worker-1',
        'status' => 'Ready',
        'schedulable' => true,
    ]);

    $cordoned = app(CordonNode::class)->execute($node);
    expect($cordoned->schedulable)->toBeFalse()->and($cordoned->status)->toBe('SchedulingDisabled');
    expect(fn () => app(CordonNode::class)->execute($cordoned))->toThrow(ValidationException::class);

    $uncordoned = app(UncordonNode::class)->execute($cordoned);
    expect($uncordoned->schedulable)->toBeTrue()->and($uncordoned->status)->toBe('Ready');

    $drained = app(DrainNode::class)->execute($uncordoned, ['force' => true, 'grace_period' => 30, 'timeout' => '5m']);
    expect($drained->schedulable)->toBeFalse()->and($drained->status)->toBe('SchedulingDisabled');
    expect(app(UncordonNode::class)->execute($drained)->schedulable)->toBeTrue();
});

it('exposes Kubernetes node roles and normalized resource quantities', function (): void {
    $node = app(RegisterKubernetesAsset::class)->execute([
        'team_id' => 'team-1',
        'kind' => 'node',
        'name' => 'control-plane-1',
        'status' => 'Ready',
        'labels' => ['node-role.kubernetes.io/control-plane' => ''],
        'capacity' => ['cpu' => '4000m', 'memory' => '8Gi'],
        'allocatable' => ['cpu' => '3500m', 'memory' => '7168Mi'],
    ]);

    expect($node->getRole())->toBe('master')
        ->and($node->getCpuCapacity())->toBe(4.0)
        ->and($node->getAllocatableCpu())->toBe(3.5)
        ->and($node->getMemoryCapacity())->toBe(8.0)
        ->and($node->getAllocatableMemory())->toBe(7.0)
        ->and($node->hasLabel('node-role.kubernetes.io/control-plane'))->toBeTrue();
});

it('updates and removes Kubernetes node labels', function (): void {
    $node = app(RegisterKubernetesAsset::class)->execute([
        'team_id' => 'team-1',
        'kind' => 'node',
        'name' => 'worker-1',
        'status' => 'Ready',
        'labels' => ['environment' => 'staging'],
    ]);

    $labeled = app(LabelNode::class)->execute($node, 'environment', 'production');
    expect($labeled->hasLabel('environment', 'production'))->toBeTrue();

    $unlabeled = app(UnlabelNode::class)->execute($labeled, 'environment');
    expect($unlabeled->hasLabel('environment'))->toBeFalse();
    expect(fn () => app(UnlabelNode::class)->execute($unlabeled, 'environment'))->toThrow(ValidationException::class);
});

it('exposes tenant-scoped Kubernetes node operations through the API', function (): void {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $user = User::factory()->create(['current_team_id' => $team->getKey()]);
    $owned = app(RegisterKubernetesAsset::class)->execute(['team_id' => $team->getKey(), 'kind' => 'node', 'name' => 'owned', 'status' => 'Ready', 'schedulable' => true]);
    $foreign = app(RegisterKubernetesAsset::class)->execute(['team_id' => $otherTeam->getKey(), 'kind' => 'node', 'name' => 'foreign', 'status' => 'Ready', 'schedulable' => true]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/control-panel/kubernetes/nodes/'.$foreign->getKey().'/cordon')
        ->assertNotFound();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/control-panel/kubernetes/nodes/'.$owned->getKey().'/cordon')
        ->assertOk()
        ->assertJsonPath('data.attributes.schedulable', false)
        ->assertJsonPath('data.attributes.status', 'SchedulingDisabled');

    expect(KubernetesNode::query()->find($owned->getKey())->schedulable)->toBeFalse();
});

it('supports tenant-owned Helm release inventory updates and deletion', function (): void {
    $cluster = app(RegisterCluster::class)->execute(['team_id' => 'team-1', 'name' => 'Primary', 'endpoint' => 'https://k8s.example.test']);
    $otherCluster = app(RegisterCluster::class)->execute(['team_id' => 'team-2', 'name' => 'Other', 'endpoint' => 'https://other.example.test']);
    $release = app(RegisterKubernetesAsset::class)->execute([
        'team_id' => 'team-1',
        'kind' => 'helm',
        'cluster_id' => $cluster->getKey(),
        'namespace' => 'production',
        'name' => 'app',
        'chart' => 'app-chart',
        'version' => '1.0.0',
        'values' => ['replicas' => 2],
    ]);

    expect($release)->toBeInstanceOf(HelmRelease::class);
    expect(fn () => app(UpdateHelmRelease::class)->execute($release, ['cluster_id' => $otherCluster->getKey()]))->toThrow(ValidationException::class);

    $updated = app(UpdateHelmRelease::class)->execute($release, ['status' => 'deployed', 'version' => '1.1.0']);
    expect($updated->status)->toBe('deployed')->and($updated->version)->toBe('1.1.0');

    app(DeleteHelmRelease::class)->execute($updated);
    expect(HelmRelease::query()->find($release->getKey()))->toBeNull();
});

it('scopes Helm release statistics to the current team', function (): void {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $user = User::factory()->create(['current_team_id' => $team->getKey()]);

    HelmRelease::query()->create(['team_id' => $team->getKey(), 'namespace' => 'default', 'name' => 'owned', 'chart' => 'app', 'status' => 'deployed']);
    HelmRelease::query()->create(['team_id' => $otherTeam->getKey(), 'namespace' => 'default', 'name' => 'foreign', 'chart' => 'app', 'status' => 'failed']);

    $this->actingAs($user);
    $method = (new ReflectionClass(HelmStatsWidget::class))->getMethod('getStats');
    $stats = $method->invoke(new HelmStatsWidget());

    expect($stats[0]->getValue())->toBe('1')
        ->and($stats[1]->getValue())->toBe('1')
        ->and($stats[2]->getValue())->toBe('0')
        ->and($stats[3]->getValue())->toBe('0');
});
