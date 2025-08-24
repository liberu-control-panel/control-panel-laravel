<?php

namespace App\Filament\App\Resources\Emails\Pages;

use App\Filament\App\Resources\Emails\EmailResource;
use Filament\Resources\Pages\ManageRecords;

class ManageEmails extends ManageRecords
{
    protected static string $resource = EmailResource::class;
}