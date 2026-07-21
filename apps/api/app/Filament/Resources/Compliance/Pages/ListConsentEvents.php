<?php

namespace App\Filament\Resources\Compliance\Pages;

use App\Filament\Resources\Compliance\ConsentEventResource;
use Filament\Resources\Pages\ListRecords;

class ListConsentEvents extends ListRecords
{
    protected static string $resource = ConsentEventResource::class;
}
