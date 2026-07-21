<?php

namespace App\Filament\Resources\Consults\Pages;

use App\Filament\Resources\Consults\ConsultResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConsults extends ListRecords
{
    protected static string $resource = ConsultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
