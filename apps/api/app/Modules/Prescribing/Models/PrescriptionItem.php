<?php

namespace App\Modules\Prescribing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['prescription_id', 'formulary_item_id', 'dosage', 'duration_days', 'instructions'])]
class PrescriptionItem extends Model
{
    protected function casts(): array
    {
        return [
            'instructions' => 'encrypted', // PHI
        ];
    }

    public function formularyItem(): BelongsTo
    {
        return $this->belongsTo(FormularyItem::class);
    }
}
