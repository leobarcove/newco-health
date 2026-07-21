<?php

namespace App\Filament\Resources\Consults\Pages;

use App\Filament\Resources\Consults\ConsultResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewConsult extends ViewRecord
{
    protected static string $resource = ConsultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
