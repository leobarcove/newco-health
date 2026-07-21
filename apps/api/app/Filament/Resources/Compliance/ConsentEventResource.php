<?php

namespace App\Filament\Resources\Compliance;

use App\Filament\Resources\Compliance\Pages\ListConsentEvents;
use App\Modules\Compliance\Models\ConsentEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** The append-only consent ledger — grants and withdrawals as events. */
class ConsentEventResource extends Resource
{
    protected static ?string $model = ConsentEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static string|UnitEnum|null $navigationGroup = 'Compliance';

    protected static ?string $pluralModelLabel = 'Consent ledger';

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
                TextColumn::make('user.name')->label('Who')->searchable(),
                TextColumn::make('kind')->badge()->color('info'),
                TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'granted' ? 'success' : 'danger'),
                TextColumn::make('ip')->label('From IP'),
            ])
            ->filters([
                SelectFilter::make('kind')->options([
                    'telemedicine_terms' => 'Telemedicine terms',
                    'privacy_policy' => 'Privacy policy',
                    'sponsor_visibility' => 'Sponsor visibility',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListConsentEvents::route('/')];
    }
}
