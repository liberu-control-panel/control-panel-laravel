<?php

declare(strict_types=1);

namespace Liberu\ControlPanel\Backups\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Liberu\ControlPanel\Backups\Enums\SnapshotStatus;

final class BackupSnapshot extends Model
{
    use HasUuids;

    protected $table = 'control_panel_backup_snapshots';

    protected $fillable = ['team_id', 'policy_id', 'location', 'status', 'size_bytes', 'checksum', 'verified_at', 'metadata'];

    protected function casts(): array
    {
        return ['status' => SnapshotStatus::class, 'size_bytes' => 'integer', 'verified_at' => 'datetime', 'metadata' => 'array'];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(BackupPolicy::class, 'policy_id');
    }

    public function restores(): HasMany
    {
        return $this->hasMany(BackupRestore::class, 'snapshot_id');
    }
}
