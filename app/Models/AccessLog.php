<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'action',
        'ip_address',
    ];

    /**
     * Get the user that owns the access log.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}