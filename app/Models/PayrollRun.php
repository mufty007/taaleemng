<?php

namespace App\Models;

use App\Enums\PayrollRunStatus;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One month’s payroll for the school. Lines hold each person’s pay.
 *
 * @property PayrollRunStatus $status
 * @property Carbon|null $paid_at
 * @property Carbon|null $voided_at
 */
#[Fillable([
    'year', 'month', 'term_id', 'status', 'gross_total', 'deductions_total', 'net_total',
    'headcount', 'prepared_by', 'paid_at', 'voided_at', 'void_reason',
])]
class PayrollRun extends Model
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
            'status' => PayrollRunStatus::class,
            'year' => 'integer',
            'month' => 'integer',
            'gross_total' => 'integer',
            'deductions_total' => 'integer',
            'net_total' => 'integer',
            'headcount' => 'integer',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PayrollLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function label(): string
    {
        return Carbon::createFromDate($this->year, $this->month, 1)->format('F Y');
    }

    public function isVoid(): bool
    {
        return $this->status === PayrollRunStatus::Voided || $this->voided_at !== null;
    }

    public function isPaid(): bool
    {
        return $this->status === PayrollRunStatus::Paid;
    }

    /**
     * Runs that still count (not voided).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function counted(Builder $query): void
    {
        $query->whereNull('voided_at')->where('status', '!=', PayrollRunStatus::Voided);
    }
}
