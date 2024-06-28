<?php

namespace App\Filament\Admin\Resources\DnsSettingResource\Pages;

use App\Filament\Admin\Resources\DnsSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDnsSettings extends ListRecords {
    protected static string $resource = DnsSettingResource::class;

    protected function getHeaderActions(): array {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
