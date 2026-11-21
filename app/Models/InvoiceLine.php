<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One line of an invoice. A discount is a line with a negative amount (kobo).
 */
#[Fillable(['invoice_id', 'fee_item_id', 'description', 'amount'])]
class InvoiceLine extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }
}
