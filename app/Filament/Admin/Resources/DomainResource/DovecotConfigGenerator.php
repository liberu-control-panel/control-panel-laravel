<?php

namespace App\Filament\Admin\Resources\EmailResource;

class DovecotConfigGenerator
{
    public function generate(string $email, string $password): string
    {
        return "user $email {\n  password = $password\n}\n";
    }
}