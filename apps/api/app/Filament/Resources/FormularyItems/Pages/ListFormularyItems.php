<?php

namespace App\Filament\Resources\FormularyItems\Pages;

use App\Filament\Resources\FormularyItems\FormularyItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFormularyItems extends ListRecords
{
    protected static string $resource = FormularyItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
