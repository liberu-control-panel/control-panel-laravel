<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'source_path',
        'destination_url',
        'redirect_type',
        'match_query_string',
        'is_regex',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'match_query_string' => 'boolean',
        'is_regex' => 'boolean',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Get the domain that owns the redirect.
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get redirect types
     */
    public static function redirectTypes(): array
    {
        return [
            '301' => 'Permanent (301)',
            '302' => 'Temporary (302)',
            '307' => 'Temporary (307)',
            '308' => 'Permanent (308)',
        ];
    }
}
