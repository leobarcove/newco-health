<?php

namespace App\Modules\Payments\Models;

use App\Models\User;
use App\Modules\Consults\Models\Consult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'purpose', 'consult_id', 'amount_kobo', 'currency', 'gateway', 'reference', 'status', 'meta', 'paid_at'])]
class Payment extends Model
{
    use HasUlids;

    public const PURPOSE_CONSULT = 'consult';
    public const PURPOSE_WALLET_TOPUP = 'wallet_topup';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function consult(): BelongsTo
    {
        return $this->belongsTo(Consult::class);
    }
}
