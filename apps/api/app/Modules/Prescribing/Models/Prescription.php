<?php

namespace App\Modules\Prescribing\Models;

use App\Modules\Consults\Models\Consult;
use App\Modules\Doctors\Models\Doctor;
use App\Modules\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['consult_id', 'doctor_id', 'patient_id', 'status', 'pickup_code', 'dispensed_at'])]
class Prescription extends Model
{
    use HasUlids;

    public const STATUS_ISSUED = 'issued';
    public const STATUS_DISPENSED = 'dispensed';
    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return ['dispensed_at' => 'datetime'];
    }

    public function consult(): BelongsTo
    {
        return $this->belongsTo(Consult::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }
}
