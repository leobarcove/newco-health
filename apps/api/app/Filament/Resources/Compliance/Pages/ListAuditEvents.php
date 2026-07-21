<?php

namespace App\Filament\Resources\Compliance\Pages;

use App\Filament\Resources\Compliance\AuditEventResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditEvents extends ListRecords
{
    protected static string $resource = AuditEventResource::class;
}
