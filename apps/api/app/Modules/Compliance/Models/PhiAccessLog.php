<?php

namespace App\Modules\Compliance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Read model over the append-only phi_access_log table (writes go via middleware). */
class PhiAccessLog extends Model
{
    protected $table = 'phi_access_log';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
