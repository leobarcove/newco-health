<?php

namespace App\Modules\Programmes\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'description', 'monthly_price_kobo', 'check_in_every_days', 'active'])]
class Programme extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
