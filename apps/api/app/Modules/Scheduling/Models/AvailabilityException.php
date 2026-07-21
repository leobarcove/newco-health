<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Doctors\Models\Doctor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['doctor_id', 'date', 'kind', 'start_time', 'end_time', 'slot_minutes', 'reason'])]
class AvailabilityException extends Model
{
    public const KIND_UNAVAILABLE = 'unavailable';
    public const KIND_EXTRA = 'extra';

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
