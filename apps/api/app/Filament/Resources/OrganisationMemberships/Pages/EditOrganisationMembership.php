<?php

namespace App\Filament\Resources\OrganisationMemberships\Pages;

use App\Filament\Resources\OrganisationMemberships\OrganisationMembershipResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrganisationMembership extends EditRecord
{
    protected static string $resource = OrganisationMembershipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
