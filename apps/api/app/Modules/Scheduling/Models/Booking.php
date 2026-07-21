<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Consults\Models\Consult;
use App\Modules\Doctors\Models\Doctor;
use App\Modules\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['patient_id', 'doctor_id', 'consult_id', 'rescheduled_from_id', 'starts_at', 'ends_at', 'state', 'complaint', 'cancelled_by', 'reminded_24h_at', 'reminded_1h_at'])]
class Booking extends Model
{
    use HasFactory, HasUlids;

    public const STATE_PENDING_PAYMENT = 'pending_payment';
    public const STATE_CONFIRMED = 'confirmed';
    public const STATE_COMPLETED = 'completed';
    public const STATE_CANCELLED = 'cancelled';
    public const STATE_NO_SHOW = 'no_show';

    /** States that hold the slot against other bookings. */
    public const SLOT_HOLDING_STATES = [self::STATE_PENDING_PAYMENT, self::STATE_CONFIRMED];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'complaint' => 'encrypted', // PHI
            'reminded_24h_at' => 'datetime',
            'reminded_1h_at' => 'datetime',
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

    public function consult(): BelongsTo
    {
        return $this->belongsTo(Consult::class);
    }

    public function isUpcoming(): bool
    {
        return $this->state === self::STATE_CONFIRMED && $this->starts_at->isFuture();
    }

    protected static function newFactory()
    {
        return \Database\Factories\BookingFactory::new();
    }
}
