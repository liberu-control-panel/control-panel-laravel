<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailAuthentication extends Model
{
    use HasFactory;

    protected $table = 'email_authentication';

    protected $fillable = [
        'domain_id',
        'spf_enabled',
        'spf_record',
        'dkim_enabled',
        'dkim_selector',
        'dkim_private_key',
        'dkim_public_key',
        'dkim_dns_record',
        'dmarc_enabled',
        'dmarc_policy',
        'dmarc_rua_email',
        'dmarc_ruf_email',
        'dmarc_percentage',
        'dmarc_record',
    ];

    protected $casts = [
        'spf_enabled' => 'boolean',
        'dkim_enabled' => 'boolean',
        'dmarc_enabled' => 'boolean',
        'dmarc_percentage' => 'integer',
    ];

    /**
     * Get the domain that owns the email authentication.
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Generate SPF record
     */
    public function generateSpfRecord(): string
    {
        $domain = $this->domain;
        return "v=spf1 mx a ip4:{$domain->server->ip_address ?? '0.0.0.0'} ~all";
    }

    /**
     * Generate DMARC record
     */
    public function generateDmarcRecord(): string
    {
        $rua = $this->dmarc_rua_email ? " rua=mailto:{$this->dmarc_rua_email}" : '';
        $ruf = $this->dmarc_ruf_email ? " ruf=mailto:{$this->dmarc_ruf_email}" : '';
        
        return "v=DMARC1; p={$this->dmarc_policy}; pct={$this->dmarc_percentage};{$rua}{$ruf}";
    }
}
