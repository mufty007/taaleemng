@php
    use App\Support\Money;

    $owed = $statement->owed();
    $class = $student->currentEnrolment?->classArm->displayName();
@endphp

<x-layouts.school-public :title="'School fees for '.$student->fullName()" :school="$school">
    <div class="flex flex-col gap-5">
        @if (session('status'))
            <div class="flex items-start gap-3 rounded-2xl border border-gold-200 bg-gold-50 px-4 py-3 text-sm text-ink" role="alert">
                <x-lucide-info class="mt-0.5 size-4 flex-none text-gold" aria-hidden="true" />
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <section class="overflow-hidden rounded-3xl border border-line bg-white" aria-labelledby="fees-title">
            <div class="flex items-center gap-4 border-b border-line p-5 sm:p-6">
                <span class="grid size-14 flex-none place-items-center rounded-2xl bg-brand-50 text-lg font-bold text-brand-600" aria-hidden="true">{{ $student->initials() }}</span>
                <div class="min-w-0">
                    <p class="kicker m-0">School fees</p>
                    <h1 id="fees-title" class="m-0 truncate text-xl font-bold tracking-[-0.02em] sm:text-2xl">{{ $student->fullName() }}</h1>
                    <p class="m-0 truncate text-sm text-muted">{{ $class ? $class.' · ' : '' }}<span class="font-mono">{{ $student->admission_no }}</span></p>
                </div>
            </div>

            @if ($owed <= 0)
                <div class="flex flex-col items-center gap-3 px-6 py-12 text-center">
                    <span class="grid size-14 place-items-center rounded-full bg-naija-50 text-naija"><x-lucide-check class="size-7" aria-hidden="true" /></span>
                    <h2 class="m-0 text-xl font-bold">All paid up</h2>
                    <p class="m-0 max-w-sm text-muted">There is nothing to pay for {{ $student->first_name }} right now. Thank you.</p>
                </div>
            @else
                <div class="flex flex-col gap-1 bg-canvas px-5 py-6 text-center sm:px-6">
                    <span class="text-sm font-medium text-muted">Amount due</span>
                    <span class="text-[40px] leading-tight font-bold tracking-[-0.03em] tabular-nums">{{ Money::format($owed) }}</span>
                    @if ($due = $statement->invoices->pluck('due_on')->filter()->min())
                        <span @class(['text-sm', 'font-semibold text-danger' => $due->isPast(), 'text-muted' => ! $due->isPast()])>{{ $due->isPast() ? 'Was due' : 'Due' }} {{ $due->format('j F Y') }}</span>
                    @endif
                </div>

                <ul class="m-0 list-none p-0">
                    @foreach ($statement->invoices as $invoice)
                        <li class="border-t border-line" x-data="{ open: false }">
                            <button type="button" class="flex w-full items-center gap-3 px-5 py-4 text-left sm:px-6" x-on:click="open = ! open" :aria-expanded="open.toString()">
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold">{{ $invoice->term->name }} · {{ $invoice->term->academicSession->name }}</span>
                                    <span class="block text-sm text-muted">Invoice {{ $invoice->invoice_no }}{{ $invoice->amount_paid > 0 ? ' · '.Money::format($invoice->amount_paid).' paid' : '' }}</span>
                                </span>
                                <span class="font-semibold tabular-nums">{{ Money::format($invoice->balance()) }}</span>
                                <x-lucide-chevron-down class="size-4 flex-none text-muted transition" ::class="open && 'rotate-180'" aria-hidden="true" />
                            </button>
                            <ul x-show="open" x-collapse x-cloak class="m-0 list-none bg-canvas px-5 pt-1 pb-4 sm:px-6">
                                @foreach ($invoice->lines as $line)
                                    <li class="flex justify-between gap-3 py-1.5 text-sm">
                                        <span class="text-muted">{{ $line->description }}</span>
                                        <span class="tabular-nums">{{ Money::format($line->amount) }}</span>
                                    </li>
                                @endforeach
                                <li class="mt-1 flex justify-between gap-3 border-t border-line pt-2 text-sm font-semibold">
                                    <span>Invoice total</span>
                                    <span class="tabular-nums">{{ Money::format($invoice->total) }}</span>
                                </li>
                            </ul>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($owed > 0)
            @if ($canPayOnline)
                <form method="POST" action="{{ request()->fullUrl() }}" class="flex flex-col gap-4 rounded-3xl border border-line bg-white p-5 sm:p-6" data-native>
                    @csrf
                    <x-field label="Your email address" name="email" type="email" :value="old('email', $guardian?->email)" hint="Paystack sends your receipt here." required autocomplete="email" />
                    <x-button variant="brand" size="lg" icon="lock" class="w-full">Pay {{ Money::format($owed) }} now</x-button>
                    <p class="m-0 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-center text-xs text-muted">
                        <x-lucide-shield-check class="size-4 text-naija" aria-hidden="true" />
                        Secured by Paystack · card, bank transfer or USSD · the school sees your payment straight away
                    </p>
                </form>
            @else
                <div class="flex flex-col gap-2 rounded-3xl border border-line bg-white p-5 sm:p-6">
                    <h2 class="m-0 text-base font-semibold">How to pay</h2>
                    <p class="m-0 text-[15px] text-muted">Pay at the school bursary, or ask the school for its bank details, and keep your receipt.</p>
                    @if ($school->phone)
                        <x-button variant="secondary" icon="phone" :href="'tel:'.$school->phone" class="mt-2 self-start">Call {{ \App\Models\User::displayPhone($school->phone) }}</x-button>
                    @endif
                </div>
            @endif
        @endif

        <p class="m-0 text-center text-xs text-muted">This page was sent to you by {{ $school->name }}. Don’t share the link with anyone you wouldn’t show your child’s fees to.</p>
    </div>
</x-layouts.school-public>
