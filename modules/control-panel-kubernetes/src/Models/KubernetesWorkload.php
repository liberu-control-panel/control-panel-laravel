<?php

declare(strict_types=1);

namespace Liberu\ControlPanel\Kubernetes\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class KubernetesWorkload extends Model
{
    use HasUuids;

    protected $table = 'control_panel_kubernetes_workloads';

    protected $fillable = ['id', 'team_id', 'cluster_id', 'namespace', 'name', 'kind', 'image', 'replicas', 'status', 'spec'];

    protected function casts(): array
    {
        return ['replicas' => 'integer', 'spec' => 'array'];
    }
}
