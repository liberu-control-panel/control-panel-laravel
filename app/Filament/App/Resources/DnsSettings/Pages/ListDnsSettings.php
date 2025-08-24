<?php

namespace App\Filament\App\Resources\DnsSettings\Pages;

use Filament\Actions\CreateAction;
use App\Filament\App\Resources\DnsSettings\DnsSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDnsSettings extends ListRecords {
    protected static string $resource = DnsSettingResource::class;

    protected function getHeaderActions(): array {
        return [
            CreateAction::make(),
        ];
    }
}
