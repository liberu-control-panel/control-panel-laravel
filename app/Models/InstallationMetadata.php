<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallationMetadata extends Model
{
    protected $table = 'installation_metadata';

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'is_editable',
    ];

    protected $casts = [
        'is_editable' => 'boolean',
    ];

    /**
     * Get a metadata value by key
     */
    public static function getValue(string $key, $default = null)
    {
        $metadata = self::where('key', $key)->first();
        
        if (!$metadata) {
            return $default;
        }

        return self::castValue($metadata->value, $metadata->type);
    }

    /**
     * Set a metadata value by key
     */
    public static function setValue(string $key, $value): bool
    {
        $metadata = self::where('key', $key)->first();
        
        if (!$metadata) {
            return false;
        }

        if (!$metadata->is_editable) {
            return false;
        }

        $metadata->value = self::prepareValue($value, $metadata->type);
        return $metadata->save();
    }

    /**
     * Update or create a metadata entry
     */
    public static function updateOrCreateMetadata(string $key, $value, array $attributes = []): self
    {
        return self::updateOrCreate(
            ['key' => $key],
            array_merge($attributes, [
                'value' => self::prepareValue($value, $attributes['type'] ?? 'string'),
            ])
        );
    }

    /**
     * Cast value based on type
     */
    protected static function castValue($value, string $type)
    {
        return match($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Prepare value for storage based on type
     */
    protected static function prepareValue($value, string $type): string
    {
        return match($type) {
            'boolean' => $value ? 'true' : 'false',
            'integer' => (string) $value,
            'json' => json_encode($value),
            default => (string) $value,
        };
    }

    /**
     * Get all metadata as key-value array
     */
    public static function getAllAsArray(): array
    {
        $metadata = self::all();
        $result = [];

        foreach ($metadata as $item) {
            $result[$item->key] = self::castValue($item->value, $item->type);
        }

        return $result;
    }
}
