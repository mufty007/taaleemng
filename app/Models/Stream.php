<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Senior secondary specialisation: Science, Arts/Humanities, Commercial, Technical.
 */
#[Fillable(['name'])]
class Stream extends Model
{
    use BelongsToSchool;
}
