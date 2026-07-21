<?php

namespace App\Filament\Resources\Consults\Tables;

use App\Modules\Consults\Models\Consult;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** The ops queue board (design plan §4.4) — state at a glance, oldest waits first. */
class ConsultsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->poll('10s')
            ->columns([
                TextColumn::make('id')
                    ->label('Consult')
                    ->limit(8)
                    ->searchable(),
                TextColumn::make('state')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Consult::STATE_QUEUED => 'warning',
                        Consult::STATE_IN_CONSULT => 'success',
                        Consult::STATE_ESCALATED => 'danger',
                        Consult::STATE_CONCLUDED, Consult::STATE_CLOSED => 'gray',
                        default => 'info',
                    }),
                TextColumn::make('patient.user.name')
                    ->label('Patient')
                    ->placeholder('—'),
                TextColumn::make('doctor.user.name')
                    ->label('Doctor')
                    ->placeholder('Unassigned'),
                TextColumn::make('queued_at')
                    ->label('Queued')
                    ->since()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('state')->options([
                    Consult::STATE_QUEUED => 'Queued',
                    Consult::STATE_IN_CONSULT => 'In consult',
                    Consult::STATE_CONCLUDED => 'Concluded',
                    Consult::STATE_ESCALATED => 'Escalated (emergency)',
                    Consult::STATE_CLOSED => 'Closed',
                    Consult::STATE_ABANDONED => 'Abandoned',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
