<?php

namespace App\Modules\Prescribing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'pcn_licence_no', 'phone', 'address', 'state_of_operation', 'status'])]
class Pharmacy extends Model
{
    use HasUlids;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
}
