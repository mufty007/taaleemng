<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Term;
use App\Support\Finance\CashflowPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Money in against money out for a term: fees collected, expenses, and what is left.
 */
class CashflowController extends Controller
{
    public function index(Request $request): View
    {
        return view('school.cashflow.index', $this->figures($request));
    }

    /**
     * The same figures as a printable, school-branded statement.
     */
    public function print(Request $request): View
    {
        return view('school.cashflow.print', $this->figures($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function figures(Request $request): array
    {
        $period = CashflowPeriod::forTerm($request->filled('term') ? Term::query()->find($request->integer('term')) : Term::current());
        $income = $period->income();
        $spending = $period->spending();

        return [
            'period' => $period,
            'terms' => Term::options(),
            'income' => $income,
            'spending' => $spending,
            'net' => $income - $spending,
            'months' => $period->months(),
            'byCategory' => $period->byCategory(),
            'byMethod' => $period->incomeByMethod(),
        ];
    }
}
