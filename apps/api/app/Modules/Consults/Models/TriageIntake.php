<?php

namespace App\Modules\Consults\Models;

use App\Modules\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['patient_id', 'complaint', 'answers', 'red_flag'])]
class TriageIntake extends Model
{
    use HasUlids;

    /**
     * Red-flag symptom keys: presence of any routes to emergency escalation,
     * never to the normal queue (startup plan §10 — the platform must not sit
     * on emergencies).
     */
    public const RED_FLAGS = [
        'chest_pain',
        'severe_breathing_difficulty',
        'uncontrolled_bleeding',
        'loss_of_consciousness',
        'seizure_now',
        'severe_abdominal_pain_pregnancy',
    ];

    protected function casts(): array
    {
        return [
            'complaint' => 'encrypted', // PHI
            'answers' => 'array',
            'red_flag' => 'boolean',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
