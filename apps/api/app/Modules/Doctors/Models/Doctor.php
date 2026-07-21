<?php

namespace App\Modules\Doctors\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'mdcn_licence_no', 'licence_expires_at', 'status', 'online'])]
class Doctor extends Model
{
    use HasFactory, HasUlids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';

    protected function casts(): array
    {
        return [
            'licence_expires_at' => 'date',
            'online' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** A doctor may only consult with an active status and an unexpired MDCN licence. */
    public function canConsult(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->licence_expires_at->isFuture();
    }

    protected static function newFactory()
    {
        return \Database\Factories\DoctorFactory::new();
    }
}
