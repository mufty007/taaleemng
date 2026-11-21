<?php

namespace App\Http\Controllers\School;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Term;
use App\Support\Files\StoredFiles;
use App\Support\Finance\CashflowPeriod;
use App\Support\Money;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Money going out: record what the school spends, with the receipt, and void mistakes with a reason.
 */
class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $period = CashflowPeriod::forTerm($this->selectedTerm($request));
        $category = ExpenseCategory::tryFrom((string) $request->query('category'));

        $inPeriod = Expense::query()->whereBetween('spent_on', [$period->from->toDateString(), $period->to->toDateString()]);

        return view('school.expenses.index', [
            'period' => $period,
            'terms' => Term::options(),
            'category' => $category,
            'categories' => ExpenseCategory::cases(),
            'expenses' => (clone $inPeriod)->with('recorder')
                ->when($category, fn (Builder $query) => $query->where('category', $category))
                ->latest('spent_on')->latest('id')
                ->paginate(30)->withQueryString(),
            'byCategory' => (clone $inPeriod)->counted()->selectRaw('category, sum(amount) as total, count(*) as items')->groupBy('category')->get()
                ->sortByDesc('total')->values(),
            'total' => (int) (clone $inPeriod)->counted()->sum('amount'),
            'methods' => collect(PaymentMethod::offline())->mapWithKeys(fn (PaymentMethod $method) => [$method->value => $method->label()])->all(),
            'canRecord' => $request->user()->can('expenses.manage'),
        ]);
    }

    public function store(Request $request, CurrentSchool $currentSchool): RedirectResponse
    {
        $data = $request->validateWithBag('expense', [
            'category' => ['required', Rule::enum(ExpenseCategory::class)],
            'description' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'string', 'max:20', 'regex:/^[₦\s\d,]*(\.\d{1,2})?$/u'],
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'paid_to' => ['nullable', 'string', 'max:120'],
            'method' => ['required', Rule::enum(PaymentMethod::class)->only(PaymentMethod::offline())],
            'reference' => ['nullable', 'string', 'max:60'],
            'receipt' => ['nullable', 'file', 'max:'.StoredFiles::MAX_KILOBYTES, 'mimes:pdf,jpg,jpeg,png,webp,heic'],
        ], ['amount.regex' => 'Enter an amount like 45,000.']);

        $amount = Money::toKobo($data['amount']);
        abort_if($amount <= 0, 422, 'The amount must be more than zero.');
        $spentOn = Carbon::parse($data['spent_on']);

        $expense = Expense::query()->create([
            ...Arr::except($data, 'receipt'),
            'amount' => $amount,
            'term_id' => CashflowPeriod::termFor($spentOn)?->id,
            'receipt' => $request->hasFile('receipt') ? StoredFiles::store([$request->file('receipt')], 'schools/'.$currentSchool->id().'/expenses')[0] : null,
            'recorded_by' => $request->user()->id,
        ]);

        activity('finance')->performedOn($expense)->log('recorded an expense of '.Money::format($amount)." ({$expense->description})");

        return back()->with('status', Money::format($amount).' recorded for '.$expense->description.'.');
    }

    public function void(Request $request, Expense $expense): RedirectResponse
    {
        abort_if($expense->isVoid(), 409);

        $data = $request->validateWithBag('voidExpense', ['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Say why it is being voided.']);

        $expense->update(['voided_at' => now(), 'void_reason' => $data['reason']]);
        activity('finance')->performedOn($expense)->log('voided the expense '.Money::format($expense->amount)." ({$expense->description}): {$data['reason']}");

        return back()->with('status', 'Expense voided. It stays on record but no longer counts.');
    }

    public function receipt(Request $request, Expense $expense): StreamedResponse
    {
        return StoredFiles::respond($expense->receipt ?? abort(404), $request->boolean('download'));
    }

    private function selectedTerm(Request $request): ?Term
    {
        return $request->filled('term') ? Term::query()->find($request->integer('term')) : Term::current();
    }
}
