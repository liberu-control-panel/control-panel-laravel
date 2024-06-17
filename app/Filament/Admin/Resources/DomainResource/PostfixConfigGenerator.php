<?php

namespace App\Filament\Admin\Resources\EmailResource;

class PostfixConfigGenerator
{
    public function generate(string $email, string $password): string
    {
        return "$email $password\n";
    }
}