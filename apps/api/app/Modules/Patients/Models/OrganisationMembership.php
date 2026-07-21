<?php

namespace App\Modules\Patients\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organisation_id', 'patient_id', 'status'])]
class OrganisationMembership extends Model
{
    use HasUlids;

    public const STATUS_ACTIVE = 'active';

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
