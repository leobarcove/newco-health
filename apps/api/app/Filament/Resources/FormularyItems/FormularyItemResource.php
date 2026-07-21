<?php

namespace App\Filament\Resources\FormularyItems;

use App\Filament\Resources\FormularyItems\Pages\CreateFormularyItem;
use App\Filament\Resources\FormularyItems\Pages\EditFormularyItem;
use App\Filament\Resources\FormularyItems\Pages\ListFormularyItems;
use App\Filament\Resources\FormularyItems\Schemas\FormularyItemForm;
use App\Filament\Resources\FormularyItems\Tables\FormularyItemsTable;
use App\Modules\Prescribing\Models\FormularyItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FormularyItemResource extends Resource
{
    protected static ?string $model = FormularyItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return FormularyItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FormularyItemsTable::configure($table);
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
            'index' => ListFormularyItems::route('/'),
            'create' => CreateFormularyItem::route('/create'),
            'edit' => EditFormularyItem::route('/{record}/edit'),
        ];
    }
}
