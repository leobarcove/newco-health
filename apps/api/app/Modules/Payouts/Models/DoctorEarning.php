<?php

namespace App\Modules\Payouts\Models;

use App\Modules\Consults\Models\Consult;
use App\Modules\Doctors\Models\Doctor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['doctor_id', 'consult_id', 'amount_kobo', 'status', 'payout_reference', 'paid_at'])]
class DoctorEarning extends Model
{
    use HasUlids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function consult(): BelongsTo
    {
        return $this->belongsTo(Consult::class);
    }
}
