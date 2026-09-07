<?php

declare(strict_types=1);

namespace Liberu\ControlPanel\Kubernetes\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class HelmRelease extends Model
{
    use HasUuids;

    protected $table = 'control_panel_kubernetes_helm_releases';

    protected $fillable = ['id', 'team_id', 'cluster_id', 'namespace', 'name', 'chart', 'version', 'values', 'status'];

    protected function casts(): array
    {
        return ['values' => 'array'];
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(HelmRevision::class, 'release_id');
    }
}
