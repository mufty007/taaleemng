<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A student's bill for a term. Amounts are in kobo.
 *
 * @property InvoiceStatus $status
 * @property Carbon $issued_at
 * @property Carbon|null $due_on
 */
#[Fillable(['invoice_no', 'student_id', 'enrolment_id', 'term_id', 'total', 'amount_paid', 'status', 'due_on', 'issued_at', 'notes'])]
class Invoice extends Model
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
            'status' => InvoiceStatus::class,
            'total' => 'integer',
            'amount_paid' => 'integer',
            'due_on' => 'date',
            'issued_at' => 'datetime',
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
     * @return BelongsTo<Enrolment, $this>
     */
    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(Enrolment::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('id');
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function balance(): int
    {
        return $this->status === InvoiceStatus::Void ? 0 : max(0, $this->total - $this->amount_paid);
    }

    /**
     * Re-read what has been paid against it and set the status to match.
     */
    public function refreshPaid(): void
    {
        $paid = (int) $this->allocations()
            ->whereHas('payment', fn ($query) => $query->where('status', 'confirmed'))
            ->sum('amount');

        $this->update([
            'amount_paid' => $paid,
            'status' => $this->status === InvoiceStatus::Void ? InvoiceStatus::Void : match (true) {
                $paid >= $this->total => InvoiceStatus::Paid,
                $paid > 0 => InvoiceStatus::PartPaid,
                default => InvoiceStatus::Unpaid,
            },
        ]);
    }
}
