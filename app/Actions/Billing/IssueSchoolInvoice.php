<?php

namespace App\Actions\Billing;

use App\Models\SchoolInvoice;
use Illuminate\Support\Facades\DB;

/**
 * Create a Ta'aleem subscription invoice with the next platform number (TAL-2026-0001).
 */
class IssueSchoolInvoice
{
    /**
     * @param  array<string, mixed>  $attributes  everything but the number and issue date
     */
    public function handle(array $attributes): SchoolInvoice
    {
        return DB::transaction(function () use ($attributes): SchoolInvoice {
            $year = now()->year;
            $sequence = SchoolInvoice::query()->whereYear('issued_on', $year)->lockForUpdate()->count() + 1;

            return SchoolInvoice::query()->create([
                ...$attributes,
                'number' => sprintf('TAL-%d-%04d', $year, $sequence),
                'issued_on' => today(),
            ]);
        });
    }
}
