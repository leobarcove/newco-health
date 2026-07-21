<?php

namespace App\Filament\Resources\Compliance;

use App\Filament\Resources\Compliance\Pages\ListPhiAccessLogs;
use App\Modules\Compliance\Models\PhiAccessLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Who read which PHI, when, from where — the NDPA accountability view. */
class PhiAccessLogResource extends Resource
{
    protected static ?string $model = PhiAccessLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEye;

    protected static string|UnitEnum|null $navigationGroup = 'Compliance';

    protected static ?string $modelLabel = 'PHI access';

    protected static ?string $pluralModelLabel = 'PHI access log';

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
                TextColumn::make('label')->label('What')->badge()->color('info'),
                TextColumn::make('subject_id')->label('Record')->limit(10)->searchable(),
                TextColumn::make('ip')->label('From IP'),
            ])
            ->filters([
                SelectFilter::make('label')->options([
                    'consult.read' => 'Consult read',
                    'consult.messages.read' => 'Messages read',
                    'prescription.read' => 'Prescription read',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPhiAccessLogs::route('/')];
    }
}
