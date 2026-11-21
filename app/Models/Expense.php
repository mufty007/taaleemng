<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money the school spent. Amounts are in kobo.
 *
 * @property ExpenseCategory $category
 * @property PaymentMethod $method
 * @property Carbon $spent_on
 * @property Carbon|null $voided_at
 * @property array{path: string, name: string, size: int, mime: string}|null $receipt
 */
#[Fillable(['term_id', 'category', 'description', 'amount', 'spent_on', 'paid_to', 'method', 'reference', 'receipt', 'recorded_by', 'voided_at', 'void_reason'])]
class Expense extends Model
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
            'category' => ExpenseCategory::class,
            'method' => PaymentMethod::class,
            'amount' => 'integer',
            'spent_on' => 'date',
            'voided_at' => 'datetime',
            'receipt' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * Expenses that count: not voided.
     *
     * @param  Builder<self>  $query
     */
    public function scopeCounted(Builder $query): void
    {
        $query->whereNull('voided_at');
    }

    public function isVoid(): bool
    {
        return $this->voided_at !== null;
    }
}
