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
        'disk_usage',
        'bandwidth_usage',
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
}

