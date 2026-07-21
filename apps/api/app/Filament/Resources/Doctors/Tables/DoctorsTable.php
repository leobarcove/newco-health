<?php

namespace App\Filament\Resources\Doctors\Tables;

use App\Modules\Doctors\Models\Doctor;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Credentialing board: licence expiry front and centre (dev plan §5.1 doctors module). */
class DoctorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Doctor')
                    ->searchable(),
                TextColumn::make('mdcn_licence_no')
                    ->label('MDCN licence')
                    ->searchable(),
                TextColumn::make('licence_expires_at')
                    ->label('Licence expires')
                    ->date('d M Y')
                    ->sortable()
                    ->color(fn (Doctor $record): string => $record->licence_expires_at->isPast()
                        ? 'danger'
                        : ($record->licence_expires_at->lt(now()->addDays(60)) ? 'warning' : 'gray')),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Doctor::STATUS_ACTIVE => 'success',
                        Doctor::STATUS_SUSPENDED => 'danger',
                        default => 'warning',
                    }),
                IconColumn::make('online')->boolean(),
            ])
            ->defaultSort('licence_expires_at')
            ->filters([
                SelectFilter::make('status')->options([
                    Doctor::STATUS_PENDING => 'Pending review',
                    Doctor::STATUS_ACTIVE => 'Active',
                    Doctor::STATUS_SUSPENDED => 'Suspended',
                ]),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
