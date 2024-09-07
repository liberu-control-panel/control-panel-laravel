<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'domains';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'domain_name',
        'registration_date',
        'expiration_date',
        'hosting_plan_id',
        'sftp_username',
        'sftp_password',
        'ssh_username',
        'ssh_password',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'registration_date' => 'date',
        'expiration_date' => 'date',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'sftp_password',
        'ssh_password',
    ];

    /**
     * Get the user that owns the domain.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the hosting plan associated with the domain.
     */
    public function hostingPlan()
    {
        return $this->belongsTo(UserHostingPlan::class);
    }

    /**
     * Get the email accounts for the domain.
     */
    public function emailAccounts()
    {
        return $this->hasMany(EmailAccount::class);
    }

    /**
     * Get the DNS settings for the domain.
     */
    public function dnsSettings()
    {
        return $this->hasMany(DnsSetting::class);
    }
}

