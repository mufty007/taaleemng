<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An actual class of students, such as JSS 2A.
 *
 * @property-read Stream|null $stream
 */
#[Fillable(['class_level_id', 'stream_id', 'form_teacher_id', 'name', 'capacity'])]
class ClassArm extends Model
{
    use BelongsToSchool, HasUlids;

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return BelongsTo<ClassLevel, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class, 'class_level_id');
    }

    /**
     * @return BelongsTo<Stream, $this>
     */
    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function formTeacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'form_teacher_id');
    }

    /**
     * @return HasMany<ClassSubject, $this>
     */
    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }

    /**
     * @return HasMany<Enrolment, $this>
     */
    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class);
    }

    /**
     * "JSS 2A", or "SSS 1 Science A" style names need the level loaded.
     * A blank arm name is a single class for that level (no A/B).
     */
    public function displayName(): string
    {
        $level = $this->level->short_name;

        // Blank name, or stream name stored for uniqueness in single-class mode.
        if ($this->name === '') {
            return $this->stream ? "{$level} {$this->stream->name}" : $level;
        }

        if ($this->relationLoaded('stream') && $this->stream && $this->name === $this->stream->name) {
            return "{$level} {$this->stream->name}";
        }

        return preg_match('/\d$/', $level) && mb_strlen($this->name) <= 2
            ? "{$level}{$this->name}"
            : "{$level} {$this->name}";
    }
}
