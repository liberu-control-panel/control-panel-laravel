<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'database_id',
        'username',
        'password',
        'host',
        'privileges',
        'is_active'
    ];

    protected $casts = [
        'privileges' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $hidden = [
        'password'
    ];

    /**
     * Available privileges
     */
    const PRIVILEGE_SELECT = 'SELECT';
    const PRIVILEGE_INSERT = 'INSERT';
    const PRIVILEGE_UPDATE = 'UPDATE';
    const PRIVILEGE_DELETE = 'DELETE';
    const PRIVILEGE_CREATE = 'CREATE';
    const PRIVILEGE_DROP = 'DROP';
    const PRIVILEGE_ALTER = 'ALTER';
    const PRIVILEGE_INDEX = 'INDEX';
    const PRIVILEGE_ALL = 'ALL PRIVILEGES';

    /**
     * Get the database that owns this user
     */
    public function database(): BelongsTo
    {
        return $this->belongsTo(Database::class);
    }

    /**
     * Get available privileges
     */
    public static function getAvailablePrivileges(): array
    {
        return [
            self::PRIVILEGE_SELECT => 'Select',
            self::PRIVILEGE_INSERT => 'Insert',
            self::PRIVILEGE_UPDATE => 'Update',
            self::PRIVILEGE_DELETE => 'Delete',
            self::PRIVILEGE_CREATE => 'Create',
            self::PRIVILEGE_DROP => 'Drop',
            self::PRIVILEGE_ALTER => 'Alter',
            self::PRIVILEGE_INDEX => 'Index',
            self::PRIVILEGE_ALL => 'All Privileges'
        ];
    }

    /**
     * Check if user has specific privilege
     */
    public function hasPrivilege(string $privilege): bool
    {
        return in_array($privilege, $this->privileges ?? []) || 
               in_array(self::PRIVILEGE_ALL, $this->privileges ?? []);
    }

    /**
     * Check if user has all privileges
     */
    public function hasAllPrivileges(): bool
    {
        return in_array(self::PRIVILEGE_ALL, $this->privileges ?? []);
    }

    /**
     * Scope for active database users
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}