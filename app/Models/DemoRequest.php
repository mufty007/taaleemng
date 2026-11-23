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
    'message', 'notes', 'ip_address', 'user_agent', 'handled_at',
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
     * Where on the marketing site this lead came from.
     */
    public function sourceLabel(): string
    {
        return match ($this->source) {
            'hero' => 'Home page (top)',
            'cta' => 'Home page (bottom)',
            'contact' => 'Contact page',
            'signup' => 'Signup page',
            default => filled($this->source) ? (string) $this->source : 'Website',
        };
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeUnhandled(Builder $query): void
    {
        $query->whereNull('handled_at');
    }
}
