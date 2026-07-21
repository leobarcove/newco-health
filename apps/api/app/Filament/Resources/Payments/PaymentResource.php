<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Finance view: every payment, with the audited staff refund action. */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

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
                TextColumn::make('created_at')->label('When')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('reference')->searchable()->limit(18),
                TextColumn::make('user.name')->label('Payer')->searchable(),
                TextColumn::make('purpose')->badge()->color('info'),
                TextColumn::make('amount_kobo')
                    ->label('Amount')
                    ->formatStateUsing(fn (int $state): string => '₦'.number_format($state / 100, 2)),
                TextColumn::make('gateway'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Payment::STATUS_SUCCEEDED => 'success',
                        Payment::STATUS_PENDING => 'warning',
                        Payment::STATUS_REFUNDED => 'gray',
                        default => 'danger',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    Payment::STATUS_PENDING => 'Pending',
                    Payment::STATUS_SUCCEEDED => 'Succeeded',
                    Payment::STATUS_FAILED => 'Failed',
                    Payment::STATUS_REFUNDED => 'Refunded',
                ]),
                SelectFilter::make('purpose')->options([
                    Payment::PURPOSE_CONSULT => 'Consult',
                    Payment::PURPOSE_BOOKING => 'Booking',
                    Payment::PURPOSE_WALLET_TOPUP => 'Wallet top-up',
                ]),
            ])
            ->recordActions([
                Action::make('refund')
                    ->label('Refund')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Wallet payments return to the sponsor wallet; gateway payments refund at the provider. Unused consults/bookings are cancelled.')
                    ->schema([
                        Textarea::make('reason')->required()->maxLength(500),
                    ])
                    ->visible(fn (Payment $record): bool => $record->status === Payment::STATUS_SUCCEEDED)
                    ->action(function (Payment $record, array $data): void {
                        app(PaymentService::class)->refund($record, auth()->id(), $data['reason']);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPayments::route('/')];
    }
}
