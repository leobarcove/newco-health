<?php

namespace App\Modules\Patients\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An employer/HMO group paying for members' care — a payer, never a tenant (ADR/dev plan §16). */
#[Fillable(['name', 'contact_email', 'status', 'balance_kobo'])]
class Organisation extends Model
{
    use HasUlids;

    public const STATUS_ACTIVE = 'active';

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganisationMembership::class);
    }
}
