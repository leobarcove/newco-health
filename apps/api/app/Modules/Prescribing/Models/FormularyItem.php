<?php

namespace App\Modules\Prescribing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'form', 'strength', 'active'])]
class FormularyItem extends Model
{
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function label(): string
    {
        return trim("{$this->name} {$this->strength} ({$this->form})");
    }
}
