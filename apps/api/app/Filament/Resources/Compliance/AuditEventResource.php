<?php

namespace App\Filament\Resources\Compliance;

use App\Filament\Resources\Compliance\Pages\ListAuditEvents;
use App\Modules\Compliance\Models\AuditEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Every state transition and money movement, immutably recorded. */
class AuditEventResource extends Resource
{
    protected static ?string $model = AuditEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Compliance';

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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d M Y H:i:s')->sortable(),
                TextColumn::make('event')->badge()->color('gray')->searchable(),
                TextColumn::make('subject_type')->label('Subject')->formatStateUsing(fn (string $state) => class_basename($state)),
                TextColumn::make('subject_id')->label('Record')->limit(10)->searchable(),
                TextColumn::make('actor.name')->label('Actor')->placeholder('system'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditEvents::route('/')];
    }
}
