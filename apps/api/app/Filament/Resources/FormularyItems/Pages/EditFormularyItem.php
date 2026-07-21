<?php

namespace App\Filament\Resources\FormularyItems\Pages;

use App\Filament\Resources\FormularyItems\FormularyItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFormularyItem extends EditRecord
{
    protected static string $resource = FormularyItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
