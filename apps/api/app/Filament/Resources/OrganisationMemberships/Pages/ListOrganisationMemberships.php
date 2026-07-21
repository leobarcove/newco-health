<?php

namespace App\Filament\Resources\OrganisationMemberships\Pages;

use App\Filament\Resources\OrganisationMemberships\OrganisationMembershipResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrganisationMemberships extends ListRecords
{
    protected static string $resource = OrganisationMembershipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
