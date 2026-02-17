<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailAlias extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'domain_id',
        'alias_address',
        'destination_addresses',
        'is_active',
    ];

    protected $casts = [
        'destination_addresses' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the email alias.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the domain that owns the email alias.
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }
}
