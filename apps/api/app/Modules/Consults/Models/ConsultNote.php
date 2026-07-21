<?php

namespace App\Modules\Consults\Models;

use App\Modules\Doctors\Models\Doctor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['consult_id', 'doctor_id', 'subjective', 'objective', 'assessment', 'plan'])]
class ConsultNote extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            // The clinical record proper — every field encrypted at rest.
            'subjective' => 'encrypted',
            'objective' => 'encrypted',
            'assessment' => 'encrypted',
            'plan' => 'encrypted',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
