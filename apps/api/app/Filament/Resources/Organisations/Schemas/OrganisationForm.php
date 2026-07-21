<?php

namespace App\Filament\Resources\Organisations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrganisationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('contact_email')
                    ->email(),
                TextInput::make('status')
                    ->required()
                    ->default('active'),
                TextInput::make('balance_kobo')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
