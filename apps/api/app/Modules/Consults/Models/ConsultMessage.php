<?php

namespace App\Modules\Consults\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['consult_id', 'sender_id', 'kind', 'body'])]
class ConsultMessage extends Model
{
    use HasUlids;

    public const KIND_TEXT = 'text';
    public const KIND_IMAGE = 'image';
    public const KIND_VOICE_NOTE = 'voice_note';
    public const KIND_SYSTEM = 'system';
    public const KIND_PRESCRIPTION = 'prescription';

    protected function casts(): array
    {
        return [
            'body' => 'encrypted', // PHI — the canonical clinical record
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
