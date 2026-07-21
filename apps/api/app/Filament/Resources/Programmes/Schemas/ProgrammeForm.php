<?php

namespace App\Filament\Resources\Programmes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProgrammeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('monthly_price_kobo')
                    ->required()
                    ->numeric(),
                TextInput::make('check_in_every_days')
                    ->required()
                    ->numeric()
                    ->default(14),
                Toggle::make('active')
                    ->required(),
            ]);
    }
}
