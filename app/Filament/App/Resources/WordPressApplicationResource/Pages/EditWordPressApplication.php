<?php

namespace App\Filament\App\Resources\WordPressApplicationResource\Pages;

use App\Filament\App\Resources\WordPressApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWordPressApplication extends EditRecord
{
    protected static string $resource = WordPressApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
