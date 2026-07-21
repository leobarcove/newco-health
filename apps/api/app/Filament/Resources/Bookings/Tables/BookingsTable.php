<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Modules\Scheduling\Models\Booking;
use App\Modules\Scheduling\Services\BookingService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('starts_at')
                    ->label('When')
                    ->dateTime('D j M Y, H:i', 'Africa/Lagos')
                    ->sortable(),
                TextColumn::make('patient.user.name')
                    ->label('Patient')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('doctor.user.name')
                    ->label('Doctor')
                    ->searchable(),
                TextColumn::make('state')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Booking::STATE_CONFIRMED => 'success',
                        Booking::STATE_COMPLETED => 'gray',
                        Booking::STATE_CANCELLED => 'warning',
                        Booking::STATE_NO_SHOW => 'danger',
                        default => 'info',
                    }),
                TextColumn::make('cancelled_by')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('state')->options([
                    Booking::STATE_CONFIRMED => 'Confirmed',
                    Booking::STATE_COMPLETED => 'Completed',
                    Booking::STATE_CANCELLED => 'Cancelled',
                    Booking::STATE_NO_SHOW => 'No-show',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('cancel')
                    ->label('Cancel booking')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->visible(fn (Booking $record): bool => $record->state === Booking::STATE_CONFIRMED)
                    ->action(function (Booking $record): void {
                        // Staff cancellations bypass the patient cutoff (ops decision, audited).
                        app(BookingService::class)->cancel($record, 'staff', auth()->id());
                    }),
            ]);
    }
}
