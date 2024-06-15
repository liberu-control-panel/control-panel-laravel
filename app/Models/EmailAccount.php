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

