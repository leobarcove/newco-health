<?php

namespace App\Filament\Resources\Pharmacies\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PharmacyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(160),
                TextInput::make('pcn_licence_no')->label('PCN licence number')->required()->unique(ignoreRecord: true),
                TextInput::make('phone')->tel(),
                TextInput::make('address')->maxLength(255),
                TextInput::make('state_of_operation')->default('Lagos'),
                Select::make('status')->options([
                    'active' => 'Active',
                    'suspended' => 'Suspended',
                ])->default('active')->required(),
            ]);
    }
}
