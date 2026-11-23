@php
    use App\Support\Money;
@endphp

<x-layouts.app title="Debtors">
    <x-page-header kicker="Finance" title="Debtors" :description="$rows->count().' '.str('student')->plural($rows->count()).' owe '.Money::format($total).' in total.'">
        <x-slot:actions>
            <x-button data-native variant="secondary" icon="download" :href="route('school.debtors.index', array_filter(['class' => $armId, 'export' => 'csv']))">Download CSV</x-button>
            @if ($canRemind && $withEmail > 0)
                <form method="POST" action="{{ route('school.fee-reminders.bulk') }}" data-confirm="Email a reminder with a pay link to the {{ $withEmail }} {{ str('parent')->plural($withEmail) }} on this list who have an email address? Anyone emailed in the last day is skipped." data-confirm-title="Email fee reminders" data-confirm-action="Send reminders" data-confirm-tone="brand">
                    @csrf
                    @if ($armId)
                        <input type="hidden" name="class" value="{{ $armId }}">
                    @endif
                    <x-button variant="brand" icon="send">Email reminders</x-button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($rows->isNotEmpty())
        <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            <x-stat-card label="Owed" :value="Money::format($total)" :hint="$rows->sum('invoices').' unpaid '.str('invoice')->plural($rows->sum('invoices'))" icon="hand-coins" tone="danger" />
            <x-stat-card label="Students owing" :value="number_format($rows->count())" :hint="$armId ? 'In this class' : 'Across the school'" icon="graduation-cap" tone="brand" />
            @if ($canRemind)
                <x-stat-card label="Reminded this week" :value="number_format($remindedThisWeek)" :hint="($rows->count() - $remindedThisWeek).' not reminded yet'" icon="bell-ring" tone="gold" />
                <x-stat-card label="Parents with email" :value="number_format($withEmail)" :hint="($rows->count() - $withEmail).' only by WhatsApp'" icon="mail" tone="sky" />
            @endif
        </div>
    @endif

    @if ($canRemind && ! $canPayOnline && $rows->isNotEmpty())
        <div class="flex flex-wrap items-start gap-3 rounded-2xl border border-gold-200 bg-gold-50 px-5 py-4 text-sm">
            <x-lucide-info class="mt-0.5 size-5 flex-none text-gold" aria-hidden="true" />
            <p class="m-0 min-w-0 flex-1 text-ink">Online payment is not switched on yet, so pay links show parents what they owe and how to pay at the school. @can('school.manage')<a href="{{ route('school.settings.edit') }}#payouts" class="font-semibold text-brand-600 hover:text-ink">Set up payouts in Settings</a> so they can pay by card, transfer or USSD.@endcan</p>
        </div>
    @endif

    <x-card :padding="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line px-5 py-4">
            <div class="flex w-48 flex-col gap-1.5">
                <label for="class" class="text-sm font-semibold">Class</label>
                <select id="class" name="class" class="field" x-data x-on:change="$el.form.requestSubmit()">
                    <option value="">All classes</option>
                    @foreach ($arms as $id => $name)<option value="{{ $id }}" @selected($armId === $id)>{{ $name }}</option>@endforeach
                </select>
            </div>
        </form>

        @if ($rows->isEmpty())
            <x-empty-state icon="hand-coins" title="Nobody owes the school" body="Students with unpaid or part-paid invoices appear here, largest balance first." />
        @else
            <ul class="m-0 list-none p-0 md:hidden">
                @foreach ($rows as $row)
                    @php
                        $guardian = $row['student']->guardians->first();
                        $reminder = $reminders->get($row['student']->id);
                        $remindData = $reminder ? [
                            'id' => $row['student']->id,
                            'name' => $row['student']->fullName(),
                            'parent' => $guardian?->fullName() ?? 'No parent on record',
                            'amount' => Money::format($row['owed']),
                            'url' => route('school.fee-reminders.store', $row['student']),
                            'link' => $reminder['link'],
                            'whatsapp' => $reminder['whatsapp'],
                                                        'email' => $reminder['email'],
                            'message' => $reminder['message'],
                        ] : null;
                        $lastText = $reminder && $reminder['last'] ? $reminder['last']->channel->label().' · '.$reminder['last']->created_at->diffForHumans() : null;
                    @endphp
                    <li class="flex items-center gap-3 border-b border-line px-4 py-3 last:border-b-0" x-data="{ last: @js($lastText) }" x-on:reminded.window="if ($event.detail.id === {{ $row['student']->id }}) last = $event.detail.label">
                        <a href="{{ route('school.students.show', $row['student']).'#fees' }}" class="min-w-0 flex-1">
                            <span class="block truncate font-semibold">{{ $row['student']->fullName() }}</span>
                            <span class="block truncate text-sm text-muted">{{ $row['student']->currentEnrolment?->classArm->displayName() ?? '—' }} · <span class="font-semibold text-ink">{{ Money::format($row['owed']) }}</span></span>
                            <span class="block truncate text-xs text-muted" x-show="last" x-text="'Reminded: ' + last"></span>
                        </a>
                        @if ($remindData)
                            <x-button type="button" variant="soft" size="sm" icon="bell-ring" x-on:click="$dispatch('remind', {{ \Illuminate\Support\Js::from($remindData) }}); $dispatch('open-drawer', 'fee-reminder')">Remind</x-button>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[860px] border-collapse text-left text-[15px]">
                    <thead>
                        <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                            <th scope="col" class="px-5 py-3 font-semibold">Student</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Class</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Parent</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Oldest due</th>
                            <th scope="col" class="px-3 py-3 text-right font-semibold">Owes</th>
                            @if ($canRemind)
                                <th scope="col" class="px-5 py-3 font-semibold"><span class="sr-only">Remind</span></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $guardian = $row['student']->guardians->first();
                                $due = $row['oldest_due'] ? \Illuminate\Support\Carbon::parse($row['oldest_due']) : null;
                                $reminder = $reminders->get($row['student']->id);
                                $remindData = $reminder ? [
                                    'id' => $row['student']->id,
                                    'name' => $row['student']->fullName(),
                                    'parent' => $guardian?->fullName() ?? 'No parent on record',
                                    'amount' => Money::format($row['owed']),
                                    'url' => route('school.fee-reminders.store', $row['student']),
                                    'link' => $reminder['link'],
                                    'whatsapp' => $reminder['whatsapp'],
                                                                        'email' => $reminder['email'],
                                    'message' => $reminder['message'],
                                ] : null;
                                $lastText = $reminder && $reminder['last'] ? $reminder['last']->channel->label().' · '.$reminder['last']->created_at->diffForHumans() : null;
                            @endphp
                            <tr class="border-t border-line hover:bg-canvas" x-data="{ last: @js($lastText) }" x-on:reminded.window="if ($event.detail.id === {{ $row['student']->id }}) last = $event.detail.label">
                                <td class="px-5 py-3">
                                    <a href="{{ route('school.students.show', $row['student']).'#fees' }}" class="font-medium hover:text-brand-600">{{ $row['student']->registerName() }}</a>
                                    <span class="block font-mono text-xs text-muted">{{ $row['student']->admission_no }}</span>
                                </td>
                                <td class="px-3 py-3">{{ $row['student']->currentEnrolment?->classArm->displayName() ?? '—' }}</td>
                                <td class="px-3 py-3 text-sm">
                                    {{ $guardian?->fullName() ?? '—' }}
                                    @if ($guardian?->phone)<a href="tel:{{ $guardian->phone }}" class="block text-xs font-medium text-brand-600">{{ \App\Models\User::displayPhone($guardian->phone) }}</a>@endif
                                    <span class="mt-0.5 flex items-center gap-1 text-xs text-muted" x-show="last" x-cloak><x-lucide-bell-ring class="size-3" aria-hidden="true" /> <span x-text="last"></span></span>
                                </td>
                                <td @class(['px-3 py-3 text-sm', 'font-semibold text-danger' => $due?->isPast()])>{{ $due?->format('j M Y') ?? '—' }}</td>
                                <td class="px-3 py-3 text-right font-bold whitespace-nowrap">{{ Money::format($row['owed']) }}</td>
                                @if ($canRemind)
                                    <td class="px-5 py-3 text-right">
                                        <x-button type="button" variant="soft" size="sm" icon="bell-ring" x-on:click="$dispatch('remind', {{ \Illuminate\Support\Js::from($remindData) }}); $dispatch('open-drawer', 'fee-reminder')">Remind</x-button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    @if ($canRemind && $rows->isNotEmpty())
        <x-drawer name="fee-reminder" title="Send a fee reminder" description="The parent gets a link to see what is owed and pay. It works for 30 days." width="sm:max-w-lg">
            <div x-data="feeReminder" x-on:remind.window="show($event.detail)" class="flex flex-col gap-5">
                <div class="flex items-center justify-between gap-3 rounded-2xl bg-canvas p-4">
                    <div class="min-w-0">
                        <div class="truncate font-semibold" x-text="row.name"></div>
                        <div class="truncate text-sm text-muted" x-text="row.parent"></div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-muted">Owes</div>
                        <div class="text-lg font-bold" x-text="row.amount"></div>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <span class="text-sm font-semibold">Message</span>
                    <p class="m-0 rounded-2xl border border-line bg-white p-4 text-sm leading-relaxed break-words text-ink" x-text="row.message"></p>
                </div>

                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <a :href="row.whatsapp" target="_blank" rel="noopener" x-show="row.whatsapp" x-on:click="record('whatsapp', 'WhatsApp · just now')" class="flex items-center gap-3 rounded-xl border border-line bg-white px-4 py-3 font-semibold transition hover:border-naija hover:text-naija">
                        <x-lucide-message-circle class="size-5 text-naija" aria-hidden="true" /> WhatsApp
                    </a>
                    <div class="flex cursor-not-allowed items-center gap-3 rounded-xl border border-dashed border-line px-4 py-3 font-semibold text-muted" aria-disabled="true" title="SMS is coming soon">
                        <x-lucide-message-square-text class="size-5" aria-hidden="true" /> SMS
                        <x-badge tone="brand" class="ml-auto">Coming soon</x-badge>
                    </div>
                    <button type="button" x-on:click="record('email', 'Email · just now')" :disabled="! row.email || busy" class="flex items-center gap-3 rounded-xl border border-line bg-white px-4 py-3 text-left font-semibold transition hover:border-brand hover:text-brand-600 disabled:cursor-not-allowed disabled:opacity-50">
                        <x-lucide-mail class="size-5 flex-none text-sky" aria-hidden="true" />
                        <span class="min-w-0">
                            <span class="block">Email</span>
                            <span class="block truncate text-xs font-normal text-muted" x-text="row.email || 'No email on file'"></span>
                        </span>
                    </button>
                    <button type="button" x-on:click="copy()" :disabled="busy" class="flex items-center gap-3 rounded-xl border border-line bg-white px-4 py-3 text-left font-semibold transition hover:border-brand hover:text-brand-600">
                        <x-lucide-link class="size-5 text-muted" aria-hidden="true" /> Copy pay link
                    </button>
                </div>

                <p x-show="! row.whatsapp" class="m-0 text-sm text-muted">There is no phone number for this parent, so WhatsApp is not available.</p>

                <div x-show="done" x-cloak class="flex items-center gap-2 rounded-xl bg-naija-50 px-4 py-3 text-sm font-semibold text-naija-700" role="status">
                    <x-lucide-check class="size-4" aria-hidden="true" /> <span x-text="'Reminder recorded: ' + done"></span>
                </div>
                <p x-show="error" x-cloak class="m-0 rounded-xl bg-danger-50 px-4 py-3 text-sm font-semibold text-danger" role="alert" x-text="error"></p>

                <a :href="row.link" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:text-ink">
                    See what the parent sees <x-lucide-external-link class="size-3.5" aria-hidden="true" />
                </a>
            </div>
        </x-drawer>
    @endif
</x-layouts.app>
