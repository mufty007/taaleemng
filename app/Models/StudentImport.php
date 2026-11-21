<?php

namespace App\Models;

use App\Enums\ImportStatus;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One uploaded student list: the rows that passed the checks and the rows that
 * did not (both encrypted, they hold children's personal details), and how far
 * the queued import has got. New logins are kept encrypted until downloaded.
 *
 * @property ImportStatus $status
 * @property list<array{line: int, student: array<string, mixed>, class_arm_id: int, guardian: array<string, mixed>}> $rows
 * @property list<array{line: int, name: string, messages: list<string>}> $problems
 * @property list<array{name: string, class: string, admission_no: string, student_login: string, student_password: string, parent: string, parent_login: string, parent_password: ?string}>|null $credentials
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
#[Fillable(['user_id', 'file_name', 'status', 'rows', 'problems', 'total_rows', 'processed_rows', 'created_count', 'credentials', 'started_at', 'finished_at'])]
class StudentImport extends Model
{
    use BelongsToSchool, HasUlids;

    /**
     * @return list<string>
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'rows' => 'encrypted:array',
            'problems' => 'encrypted:array',
            'credentials' => 'encrypted:array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function percentDone(): int
    {
        return $this->total_rows === 0 ? 0 : (int) floor($this->processed_rows / $this->total_rows * 100);
    }
}
