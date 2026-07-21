<?php

namespace App\Filament\Resources\Consults;

use App\Filament\Resources\Consults\Pages\ListConsults;
use App\Filament\Resources\Consults\Pages\ViewConsult;
use App\Filament\Resources\Consults\Schemas\ConsultInfolist;
use App\Filament\Resources\Consults\Tables\ConsultsTable;
use App\Modules\Consults\Models\Consult;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Read-only for staff: the consult record is clinical evidence — it is never
 * created or edited from the backoffice (CLAUDE.md rule 5, ADR-002).
 */
class ConsultResource extends Resource
{
    protected static ?string $model = Consult::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConsultInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConsultsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConsults::route('/'),
            'view' => ViewConsult::route('/{record}'),
        ];
    }
}
