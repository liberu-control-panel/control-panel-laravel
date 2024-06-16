<?php

namespace App\Models;

use App\Events\DnsSettingSaved;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DnsSetting extends Model
{
    use HasFactory;

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::saved(function ($dnsSetting) {
            event(new DnsSettingSaved($dnsSetting));
        });
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'dns_settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'domain_id',
        'record_type',
        'name',
        'value',
        'ttl',
    ];
    
    /**
     * The validation rules for the model attributes.
     *
     * @var array
     */
    public $rules = [
        'record_type' => 'required|in:A,MX',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'ttl' => 'integer',
    ];

    /**
     * Get the domain that owns the DNS setting.
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }
}

