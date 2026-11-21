<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Money received for a student, in kobo. Confirmed payments get a receipt
 * number and are allocated to the student's oldest unpaid invoices first.
 *
 * @property PaymentMethod $method
 * @property PaymentStatus $status
 * @property Carbon|null $paid_at
 * @property array<string, mixed>|null $meta
 * @property-read int|string|null $allocations_sum_amount when loaded with withSum('allocations', 'amount')
 */
#[Fillable(['receipt_no', 'student_id', 'guardian_id', 'amount', 'method', 'reference', 'status', 'paid_at', 'recorded_by', 'notes', 'meta'])]
class Payment extends Model
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
            'amount' => 'integer',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * Money not yet applied to any invoice (an advance or overpayment), in kobo.
     */
    public function unallocated(): int
    {
        return max(0, $this->amount - (int) $this->allocations()->sum('amount'));
    }
}
