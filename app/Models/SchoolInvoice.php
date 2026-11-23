<?php

namespace App\Models;

use App\Enums\SchoolInvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ta'aleem's invoice to a school for its subscription (students × price per student).
 * Platform-level: kept by the platform, not inside the school's own books.
 *
 * @property SchoolInvoiceStatus $status
 * @property Carbon $issued_on
 * @property Carbon $due_on
 * @property Carbon|null $paid_at
 */
#[Fillable([
    'school_id', 'number', 'period', 'students', 'unit_price', 'amount', 'status', 'issued_on', 'due_on',
    'paid_at', 'payment_method', 'payment_reference', 'notes', 'created_by',
])]
class SchoolInvoice extends Model
{
    use HasUlids;

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
            'status' => SchoolInvoiceStatus::class,
            'students' => 'integer',
            'unit_price' => 'integer',
            'amount' => 'integer',
            'issued_on' => 'date',
            'due_on' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function isOverdue(): bool
    {
        return $this->status === SchoolInvoiceStatus::Sent && $this->due_on->isPast() && ! $this->due_on->isToday();
    }
}
