<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomErrorPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'error_code',
        'custom_content',
        'custom_file_path',
        'is_active',
    ];

    protected $casts = [
        'error_code' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the domain that owns the custom error page.
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get common error codes
     */
    public static function commonErrorCodes(): array
    {
        return [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            408 => 'Request Timeout',
            410 => 'Gone',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
        ];
    }
}
