<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HostingPlan extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'hosting_plans';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'disk_space',
        'bandwidth',
        'price',
        'cpu_limit',
        'memory_limit',
        'max_databases',
        'max_email_accounts',
        'max_subdomains',
        'max_ftp_accounts',
        'php_versions',
        'features',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'price' => 'decimal:2',
        'php_versions' => 'array',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

public function userHostingPlans()
{
    return $this->hasMany(UserHostingPlan::class);
}

}

