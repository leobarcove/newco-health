<?php

namespace App\Filament\Resources\Pharmacies;

use App\Filament\Resources\Pharmacies\Pages\CreatePharmacy;
use App\Filament\Resources\Pharmacies\Pages\EditPharmacy;
use App\Filament\Resources\Pharmacies\Pages\ListPharmacies;
use App\Filament\Resources\Pharmacies\Schemas\PharmacyForm;
use App\Filament\Resources\Pharmacies\Tables\PharmaciesTable;
use App\Modules\Prescribing\Models\Pharmacy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PharmacyResource extends Resource
{
    protected static ?string $model = Pharmacy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PharmacyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PharmaciesTable::configure($table);
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
            'index' => ListPharmacies::route('/'),
            'create' => CreatePharmacy::route('/create'),
            'edit' => EditPharmacy::route('/{record}/edit'),
        ];
    }
}
