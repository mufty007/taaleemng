<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Someone has seen an announcement, so it no longer counts on their bell.
 *
 * @property Carbon $read_at
 */
#[Fillable(['announcement_id', 'user_id', 'read_at'])]
class AnnouncementRead extends Model
{
    use BelongsToSchool;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }
}
