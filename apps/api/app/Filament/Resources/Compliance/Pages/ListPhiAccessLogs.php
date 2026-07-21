<?php

namespace App\Filament\Resources\Compliance\Pages;

use App\Filament\Resources\Compliance\PhiAccessLogResource;
use Filament\Resources\Pages\ListRecords;

class ListPhiAccessLogs extends ListRecords
{
    protected static string $resource = PhiAccessLogResource::class;
}
