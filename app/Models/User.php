<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use JoelButcher\Socialstream\HasConnectedAccounts;
use JoelButcher\Socialstream\SetsProfilePhotoFromUrl;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasConnectedAccounts;
    use HasFactory;
    use HasProfilePhoto {
        HasProfilePhoto::profilePhotoUrl as getPhotoUrl;
    }
    use Notifiable;
    use HasRoles;
    use SetsProfilePhotoFromUrl;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    /**
     * Get the URL to the user's profile photo.
     */
    public function profilePhotoUrl(): Attribute
    {
        return filter_var($this->profile_photo_path, FILTER_VALIDATE_URL)
            ? Attribute::get(fn () => $this->profile_photo_path)
            : $this->getPhotoUrl();
    }

	public function userHostingPlans()
	{
    return $this->hasMany(UserHostingPlan::class);
	}

public function domains()
{
    return $this->hasMany(Domain::class);
}

public function emailAccounts()
{
    return $this->hasMany(EmailAccount::class);
}

public function resourceUsages()
{
    return $this->hasMany(ResourceUsage::class);
}

public function hasReachedDockerComposeLimit(): bool
{
    $currentPlan = $this->currentHostingPlan();

    if (!$currentPlan) {
        return true; // Default to true if no hosting plan is found
    }

    if ($currentPlan->name === 'free') {
        return $this->domains()->count() >= 1;
    }

    if ($currentPlan->name === 'premium') {
        return false; // Unlimited instances for premium plan
    }

    return true; // Default to true for other plans
}

public function currentHostingPlan()
{
    return $this->userHostingPlans()->latest()->first();
}

public function databases()
{
    return $this->hasMany(Database::class);
}



}


