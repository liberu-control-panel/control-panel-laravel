<?php

namespace App\Filament\App\Resources\WordPressApplicationResource\Pages;

use App\Filament\App\Resources\WordPressApplicationResource;
use Filament\Resources\Pages\ListRecords;

class ListWordPressApplications extends ListRecords
{
    protected static string $resource = WordPressApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
