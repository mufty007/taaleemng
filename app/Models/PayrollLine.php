<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person’s pay on a payroll run. Amounts are in kobo, snapped when prepared.
 *
 * @property Carbon|null $paid_at
 */
#[Fillable([
    'payroll_run_id', 'staff_id', 'staff_name', 'staff_no', 'designation',
    'basic_salary', 'allowances', 'deductions', 'net_pay',
    'bank_name', 'bank_account_name', 'bank_account_number', 'paid_at',
])]
class PayrollLine extends Model
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
            'basic_salary' => 'integer',
            'allowances' => 'integer',
            'deductions' => 'integer',
            'net_pay' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function gross(): int
    {
        return $this->basic_salary + $this->allowances;
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }
}
