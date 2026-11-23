@php
    use App\Support\Money;

    $school = $invoice->school;
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->number }} · Ta’aleem</title>
    @vite(['resources/css/app.css'])
    <style>@page { size: A4; margin: 14mm; }</style>
</head>
<body class="bg-white text-ink">
    <main class="mx-auto max-w-[760px] px-6 py-10">
        <header class="flex items-start justify-between gap-6 border-b border-line pb-6">
            <div>
                <x-logo class="h-8" />
                <p class="m-0 mt-2 text-sm text-muted">{{ config('taaleem.marketing.contact_email') }}</p>
            </div>
            <div class="text-right">
                <p class="m-0 text-2xl font-bold tracking-[-0.02em]">Invoice</p>
                <p class="m-0 font-mono text-sm">{{ $invoice->number }}</p>
                <p class="m-0 mt-1"><x-badge :tone="$invoice->status->tone()">{{ $invoice->status->label() }}</x-badge></p>
            </div>
        </header>

        <section class="mt-6 grid grid-cols-2 gap-6 text-sm">
            <div>
                <p class="m-0 text-xs font-semibold tracking-wide text-muted uppercase">Billed to</p>
                <p class="m-0 mt-1 font-semibold">{{ $school->name }}</p>
                <p class="m-0 text-muted">{{ $school->address }}{{ $school->state ? ', '.$school->state : '' }}</p>
                <p class="m-0 text-muted">{{ $school->email }}</p>
            </div>
            <div class="text-right">
                <p class="m-0"><span class="text-muted">Issued</span> {{ $invoice->issued_on->format('j F Y') }}</p>
                <p class="m-0"><span class="text-muted">Due</span> <strong>{{ $invoice->due_on->format('j F Y') }}</strong></p>
                @if ($invoice->paid_at)<p class="m-0"><span class="text-muted">Paid</span> {{ $invoice->paid_at->format('j F Y') }} · {{ $invoice->payment_method }}</p>@endif
            </div>
        </section>

        <table class="mt-8 w-full border-collapse text-sm">
            <thead>
                <tr class="border-b border-line text-left text-xs tracking-wide text-muted uppercase">
                    <th class="py-2 font-semibold">Description</th>
                    <th class="py-2 text-right font-semibold">Students</th>
                    <th class="py-2 text-right font-semibold">Price</th>
                    <th class="py-2 text-right font-semibold">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-line">
                    <td class="py-3">Ta’aleem {{ $school->plan->label() }} plan · {{ $invoice->period }}</td>
                    <td class="py-3 text-right tabular-nums">{{ number_format($invoice->students) }}</td>
                    <td class="py-3 text-right tabular-nums">{{ Money::format($invoice->unit_price) }}</td>
                    <td class="py-3 text-right font-semibold tabular-nums">{{ Money::format($invoice->amount) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="pt-4 text-right font-semibold">Total due</td>
                    <td class="pt-4 text-right text-lg font-bold tabular-nums">{{ Money::format($invoice->amount) }}</td>
                </tr>
            </tfoot>
        </table>

        @if ($invoice->notes)
            <p class="mt-6 rounded-xl bg-canvas p-4 text-sm">{{ $invoice->notes }}</p>
        @endif

        <p class="mt-10 text-sm text-muted">Pay by bank transfer and send the reference to {{ config('taaleem.marketing.contact_email') }}. Thank you for using Ta’aleem.</p>
        <button type="button" onclick="window.print()" class="mt-6 rounded-xl bg-ink px-4 py-2.5 text-sm font-semibold text-white print:hidden">Print or save as PDF</button>
    </main>
</body>
</html>
