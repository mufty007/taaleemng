<?php

namespace App\Models;

use App\Enums\DemoRequestKind;
use Database\Factories\DemoRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A lead from the marketing site (demo booking, trial request or question).
 * Platform-level: not owned by any school.
 */
#[Fillable([
    'kind', 'source', 'name', 'email', 'phone', 'school_name', 'student_count',
    'message', 'ip_address', 'user_agent', 'handled_at',
])]
class DemoRequest extends Model
{
    /** @use HasFactory<DemoRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DemoRequestKind::class,
            'handled_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeUnhandled(Builder $query): void
    {
        $query->whereNull('handled_at');
    }
}
