<?php

namespace App\Filament\Admin\Resources\EmailResource;

use App\Models\Email;

class PostfixConfigGenerator
{
    public function generate(Email $email): string
    {
        // Generate Postfix configuration based on $email  
        // ...
    }
}