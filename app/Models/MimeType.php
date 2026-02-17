<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MimeType extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'extension',
        'mime_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the domain that owns the MIME type.
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get common MIME types
     */
    public static function commonMimeTypes(): array
    {
        return [
            '.webp' => 'image/webp',
            '.svg' => 'image/svg+xml',
            '.woff' => 'font/woff',
            '.woff2' => 'font/woff2',
            '.ttf' => 'font/ttf',
            '.otf' => 'font/otf',
            '.mp4' => 'video/mp4',
            '.webm' => 'video/webm',
            '.ogg' => 'video/ogg',
            '.mp3' => 'audio/mpeg',
            '.wav' => 'audio/wav',
            '.json' => 'application/json',
            '.xml' => 'application/xml',
        ];
    }
}
