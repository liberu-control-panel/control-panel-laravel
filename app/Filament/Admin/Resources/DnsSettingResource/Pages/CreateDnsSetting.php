<?php

namespace App\Filament\Admin\Resources\DnsSettingResource\Pages;

use App\Filament\Admin\Resources\DnsSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDnsSetting extends CreateRecord {
    protected static string $resource = DnsSettingResource::class;
}
