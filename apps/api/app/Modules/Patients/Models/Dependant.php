<?php

namespace App\Modules\Patients\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['patient_id', 'name', 'relationship', 'date_of_birth', 'sex'])]
class Dependant extends Model
{
    use HasUlids;

    public const RELATIONSHIPS = ['child', 'parent', 'spouse', 'other'];

    protected function casts(): array
    {
        return [
            'name' => 'encrypted', // PHI
            'date_of_birth' => 'date',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
