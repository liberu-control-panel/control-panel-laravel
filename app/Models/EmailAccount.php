<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailAccount extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'email_accounts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'domain_id',
        'email_address',
        'password',
        'quota',
        'forwarding_rules',
        'autoresponder_enabled',
        'autoresponder_subject',
        'autoresponder_message',
        'autoresponder_start_date',
        'autoresponder_end_date',
        'spam_filter_enabled',
        'spam_threshold',
        'spam_action',
        'keep_copy_on_server',
    ];

    protected $casts = [
        'forwarding_rules' => 'array',
        'autoresponder_enabled' => 'boolean',
        'autoresponder_start_date' => 'datetime',
        'autoresponder_end_date' => 'datetime',
        'spam_filter_enabled' => 'boolean',
        'spam_threshold' => 'integer',
        'keep_copy_on_server' => 'boolean',
    ];

    /**
     * Get the user that owns the email account.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the domain that owns the email account.
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }
}

