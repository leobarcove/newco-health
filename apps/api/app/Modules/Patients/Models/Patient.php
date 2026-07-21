<?php

namespace App\Modules\Patients\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'date_of_birth', 'sex', 'medical_notes'])]
class Patient extends Model
{
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'medical_notes' => 'encrypted', // PHI
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory()
    {
        return \Database\Factories\PatientFactory::new();
    }
}
