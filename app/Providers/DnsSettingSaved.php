<?php

namespace App\Events;

use App\Models\DnsSetting;
use Illuminate\Foundation\Events\Dispatchable;

class DnsSettingSaved
{
    use Dispatchable;

    /**
     * The DnsSetting instance.
     *
     * @var \App\Models\DnsSetting
     */
    public $dnsSetting;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\DnsSetting  $dnsSetting
     * @return void
     */
    public function __construct(DnsSetting $dnsSetting)
    {
        $this->dnsSetting = $dnsSetting;
    }
}