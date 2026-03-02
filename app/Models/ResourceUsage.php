<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResourceUsage extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'resource_usage';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'domain_id',
        'disk_usage',
        'bandwidth_usage',
        'cpu_usage',
        'memory_usage',
        'month',
        'year',
    ];

    /**
     * Get the user that owns the resource usage.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the domain associated with this resource usage snapshot.
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Scope to filter by a specific domain.
     */
    public function scopeForDomain($query, int $domainId)
    {
        return $query->where('domain_id', $domainId);
    }

    /**
     * Scope to filter by a specific month and year.
     */
    public function scopeForMonth($query, int $month, int $year)
    {
        return $query->where('month', $month)->where('year', $year);
    }
}

