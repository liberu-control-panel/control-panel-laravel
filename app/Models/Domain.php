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
        'server_id',
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
     * Get the server where this domain is hosted.
     */
    public function server()
    {
        return $this->belongsTo(Server::class);
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

    /**
     * Get the containers for the domain.
     */
    public function containers()
    {
        return $this->hasMany(Container::class);
    }

    /**
     * Get the databases for the domain.
     */
    public function databases()
    {
        return $this->hasMany(Database::class);
    }

    /**
     * Get the subdomains for the domain.
     */
    public function subdomains()
    {
        return $this->hasMany(Subdomain::class);
    }

    /**
     * Get the SSL certificates for the domain.
     */
    public function sslCertificates()
    {
        return $this->hasMany(SslCertificate::class);
    }

    /**
     * Get the cron jobs for the domain.
     */
    public function cronJobs()
    {
        return $this->hasMany(CronJob::class);
    }

    /**
     * Get the backups for the domain.
     */
    public function backups()
    {
        return $this->hasMany(Backup::class);
    }

    /**
     * Check if domain has active containers
     */
    public function hasActiveContainers(): bool
    {
        return $this->containers()->where('status', Container::STATUS_RUNNING)->exists();
    }

    /**
     * Get the main web container
     */
    public function getWebContainer()
    {
        return $this->containers()->where('type', Container::TYPE_WEB)->first();
    }

    /**
     * Get the database container
     */
    public function getDatabaseContainer()
    {
        return $this->containers()->where('type', Container::TYPE_DATABASE)->first();
    }

    /**
     * Get domain resource usage
     */
    public function getResourceUsage(): array
    {
        $containers = $this->containers()->running()->get();
        $totalCpu = 0;
        $totalMemory = 0;

        foreach ($containers as $container) {
            $usage = $container->getResourceUsage();
            $totalCpu += $usage['cpu_percent'];
            $totalMemory += $usage['memory_usage'];
        }

        return [
            'cpu_percent' => $totalCpu,
            'memory_usage' => $totalMemory,
            'container_count' => $containers->count()
        ];
    }

    /**
     * Get WordPress applications for the domain
     */
    public function wordpressApplications()
    {
        return $this->hasMany(WordPressApplication::class);
    }

    /**
     * Get git deployments for the domain
     */
    public function gitDeployments()
    {
        return $this->hasMany(GitDeployment::class);
    }
}

