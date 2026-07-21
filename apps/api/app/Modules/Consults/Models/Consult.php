<?php

namespace App\Modules\Consults\Models;

use App\Modules\Doctors\Models\Doctor;
use App\Modules\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['patient_id', 'dependant_id', 'doctor_id', 'triage_intake_id', 'state', 'modality', 'daily_room', 'queued_at', 'assigned_at', 'concluded_at'])]
class Consult extends Model
{
    use HasFactory, HasUlids;

    public const STATE_REQUESTED = 'requested';
    public const STATE_TRIAGED = 'triaged';
    public const STATE_QUEUED = 'queued';
    public const STATE_ASSIGNED = 'assigned';
    public const STATE_IN_CONSULT = 'in_consult';
    public const STATE_CONCLUDED = 'concluded';
    public const STATE_CLOSED = 'closed';
    public const STATE_ESCALATED = 'escalated_emergency';
    public const STATE_ABANDONED = 'abandoned';

    public const MODALITIES = ['chat', 'voice', 'video'];

    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'assigned_at' => 'datetime',
            'concluded_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function intake(): BelongsTo
    {
        return $this->belongsTo(TriageIntake::class, 'triage_intake_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConsultMessage::class)->orderBy('created_at');
    }

    protected static function newFactory()
    {
        return \Database\Factories\ConsultFactory::new();
    }
}
