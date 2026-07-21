<?php

namespace App\Modules\Patients\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sponsor_user_id', 'patient_id', 'status', 'beneficiary_label', 'responded_at'])]
class Sponsorship extends Model
{
    use HasUlids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_PAUSED = 'paused';

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sponsor_user_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
