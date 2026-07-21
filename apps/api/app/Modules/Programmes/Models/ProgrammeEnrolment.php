<?php

namespace App\Modules\Programmes\Models;

use App\Modules\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['programme_id', 'patient_id', 'status', 'current_period_ends_at', 'next_check_in_at', 'last_nudged_at'])]
class ProgrammeEnrolment extends Model
{
    use HasUlids;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_LAPSED = 'lapsed';
    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'current_period_ends_at' => 'datetime',
            'next_check_in_at' => 'datetime',
            'last_nudged_at' => 'datetime',
        ];
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
