<?php

namespace App\Modules\Compliance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Read model over the append-only consents ledger (writes go via ConsentLedger). */
class ConsentEvent extends Model
{
    protected $table = 'consents';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'meta' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
