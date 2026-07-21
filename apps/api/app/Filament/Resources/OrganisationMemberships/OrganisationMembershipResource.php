<?php

namespace App\Filament\Resources\OrganisationMemberships;

use App\Filament\Resources\OrganisationMemberships\Pages\CreateOrganisationMembership;
use App\Filament\Resources\OrganisationMemberships\Pages\EditOrganisationMembership;
use App\Filament\Resources\OrganisationMemberships\Pages\ListOrganisationMemberships;
use App\Filament\Resources\OrganisationMemberships\Schemas\OrganisationMembershipForm;
use App\Filament\Resources\OrganisationMemberships\Tables\OrganisationMembershipsTable;
use App\Modules\Patients\Models\OrganisationMembership;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrganisationMembershipResource extends Resource
{
    protected static ?string $model = OrganisationMembership::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return OrganisationMembershipForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrganisationMembershipsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganisationMemberships::route('/'),
            'create' => CreateOrganisationMembership::route('/create'),
            'edit' => EditOrganisationMembership::route('/{record}/edit'),
        ];
    }
}
