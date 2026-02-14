<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ServerCredential extends Model
{
    use HasFactory;

    const AUTH_TYPE_PASSWORD = 'password';
    const AUTH_TYPE_SSH_KEY = 'ssh_key';
    const AUTH_TYPE_BOTH = 'both';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'server_id',
        'username',
        'auth_type',
        'password',
        'ssh_private_key',
        'ssh_public_key',
        'ssh_key_passphrase',
        'is_active',
        'last_used_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'ssh_private_key',
        'ssh_key_passphrase',
    ];

    /**
     * Get the server that owns the credential.
     */
    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Encrypt and set the password.
     */
    public function setPasswordAttribute($value)
    {
        if ($value) {
            $this->attributes['password'] = Crypt::encryptString($value);
        }
    }

    /**
     * Decrypt and get the password.
     */
    public function getPasswordAttribute($value)
    {
        if ($value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Encrypt and set the SSH private key.
     */
    public function setSshPrivateKeyAttribute($value)
    {
        if ($value) {
            $this->attributes['ssh_private_key'] = Crypt::encryptString($value);
        }
    }

    /**
     * Decrypt and get the SSH private key.
     */
    public function getSshPrivateKeyAttribute($value)
    {
        if ($value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Encrypt and set the SSH key passphrase.
     */
    public function setSshKeyPassphraseAttribute($value)
    {
        if ($value) {
            $this->attributes['ssh_key_passphrase'] = Crypt::encryptString($value);
        }
    }

    /**
     * Decrypt and get the SSH key passphrase.
     */
    public function getSshKeyPassphraseAttribute($value)
    {
        if ($value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Check if credential uses password authentication.
     */
    public function usesPassword(): bool
    {
        return in_array($this->auth_type, [self::AUTH_TYPE_PASSWORD, self::AUTH_TYPE_BOTH]);
    }

    /**
     * Check if credential uses SSH key authentication.
     */
    public function usesSshKey(): bool
    {
        return in_array($this->auth_type, [self::AUTH_TYPE_SSH_KEY, self::AUTH_TYPE_BOTH]);
    }

    /**
     * Update last used timestamp.
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Get available authentication types.
     */
    public static function getAuthTypes(): array
    {
        return [
            self::AUTH_TYPE_PASSWORD => 'Password',
            self::AUTH_TYPE_SSH_KEY => 'SSH Key',
            self::AUTH_TYPE_BOTH => 'Both',
        ];
    }
}
