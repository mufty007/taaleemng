<x-layouts.marketing title="School management for Nigerian schools">

    {{-- HERO --}}
    <section id="hero" class="mx-auto grid max-w-[1200px] grid-cols-1 items-center gap-14 px-4 pt-14 pb-10 sm:px-6 sm:pt-[72px] lg:grid-cols-2">
        <div class="flex flex-col gap-[26px]" data-enter>
            <div class="inline-flex items-center gap-2.5 self-start rounded-full border border-line bg-white py-1.5 pr-3.5 pl-1.5 text-sm font-medium">
                <span class="rounded-full bg-naija px-2.5 py-[3px] text-xs font-semibold tracking-[0.04em] text-white">NIGERIA</span>
                For nursery, primary, secondary &amp; Islamiyya schools
            </div>

            <h1 class="m-0 text-[clamp(44px,6.2vw,76px)] leading-[1.02] font-bold tracking-tightest text-balance">
                Your whole school, from admission to
                <span class="mx-1 mt-1.5 inline-block -rotate-[2.5deg] rounded-[18px] bg-brand-600 px-[18px] pb-1 text-white">report card.</span>
            </h1>

            <p class="m-0 max-w-[540px] text-[19px] leading-[1.55] text-pretty text-muted">
                Enrolment, school fees, CBT exams, results, payroll and attendance in one system, built around how Nigerian schools run: sessions, three terms, CA and exams.
            </p>

            <x-marketing.lead-form source="hero" />

            <ul class="m-0 flex list-none flex-wrap gap-5 p-0 text-sm text-muted">
                @foreach (['Set up before resumption', 'We move your records for you', 'Fees in Naira'] as $promise)
                    <li class="flex items-center gap-2"><span class="size-2 rounded-full bg-naija" aria-hidden="true"></span>{{ $promise }}</li>
                @endforeach
            </ul>
        </div>

        {{-- Hero mock: an interactive results broadsheet --}}
        <div class="relative px-3 pt-7 pb-14">
            <div class="absolute inset-x-0 top-0 bottom-7 left-10 rotate-3 rounded-4xl bg-brand-100" aria-hidden="true"></div>

            <div x-data="broadsheet" class="relative overflow-hidden rounded-[22px] border border-line bg-white shadow-panel">
                <div class="flex items-center gap-1.5 border-b border-line bg-surface px-4 py-3" aria-hidden="true">
                    <span class="size-2.5 rounded-full bg-[#F2B8B5]"></span>
                    <span class="size-2.5 rounded-full bg-[#F6DE9C]"></span>
                    <span class="size-2.5 rounded-full bg-[#B9E3C1]"></span>
                    <span class="ml-3 rounded-lg bg-chip px-3 py-1 text-xs text-muted">app.taaleem.ng/results</span>
                </div>

                <div class="flex flex-col gap-3.5 px-5 pt-5 pb-4">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <div class="text-[13px] text-muted">2026/2027 Session · Mathematics</div>
                            <div class="text-[22px] font-bold tracking-[-0.02em]">JSS 2A · Broadsheet</div>
                        </div>
                        <div class="flex gap-1 rounded-xl bg-chip p-1" role="group" aria-label="Term">
                            <template x-for="(label, index) in terms" :key="label">
                                <button
                                    type="button"
                                    @click="term = index"
                                    :aria-pressed="(term === index).toString()"
                                    :class="term === index ? 'bg-white text-ink shadow-sm' : 'text-muted hover:text-ink'"
                                    class="rounded-[9px] px-[11px] py-[7px] text-[13px] font-semibold transition"
                                    x-text="label"
                                ></button>
                            </template>
                        </div>
                    </div>

                    <div class="text-[13px]" role="table" aria-label="JSS 2A Mathematics broadsheet">
                        <div role="row" class="grid grid-cols-[minmax(0,2.2fr)_repeat(3,minmax(0,1fr))_minmax(0,1.1fr)_minmax(0,0.9fr)] gap-1.5 px-2.5 py-2 text-[11px] font-semibold tracking-[0.05em] text-muted">
                            <span role="columnheader">STUDENT</span>
                            <span role="columnheader">CA /40</span>
                            <span role="columnheader">EXAM /60</span>
                            <span role="columnheader">TOTAL</span>
                            <span role="columnheader">GRADE</span>
                            <span role="columnheader">POS.</span>
                        </div>
                        <template x-for="row in rows" :key="row.name">
                            <div role="row" class="grid grid-cols-[minmax(0,2.2fr)_repeat(3,minmax(0,1fr))_minmax(0,1.1fr)_minmax(0,0.9fr)] items-center gap-1.5 border-t border-line px-2.5 py-[9px] text-sm">
                                <span role="cell" class="flex min-w-0 items-center gap-2">
                                    <span class="grid size-7 flex-none place-items-center rounded-full text-[11px] font-semibold" :style="{ background: row.tint }" x-text="row.initials" aria-hidden="true"></span>
                                    <span class="truncate font-medium" x-text="row.name"></span>
                                </span>
                                <span role="cell" x-text="row.ca"></span>
                                <span role="cell" x-text="row.exam"></span>
                                <span role="cell" class="font-bold" x-text="row.total"></span>
                                <span role="cell"><span class="rounded-full px-[9px] py-[3px] text-xs font-semibold" :class="row.gradeClass" x-text="row.grade"></span></span>
                                <span role="cell" class="font-semibold text-brand-600" x-text="row.position"></span>
                            </div>
                        </template>
                    </div>
                    <div class="text-center text-xs text-muted">Switch terms to see totals, grades and positions recalculate</div>
                </div>
            </div>

            <div data-float class="absolute -bottom-2 -left-1.5 flex -rotate-2 items-center gap-3 rounded-2xl border border-line bg-white px-4 py-3 shadow-float" aria-hidden="true">
                <span class="grid size-9 place-items-center rounded-[10px] bg-naija-50 font-bold text-naija">₦</span>
                <div>
                    <div class="text-sm font-semibold">First Term fees received</div>
                    <div class="text-xs text-muted">Chinedu’s parent · ₦185,000 · transfer</div>
                </div>
            </div>
            <div data-float class="absolute top-0 -right-1 hidden max-w-[230px] rotate-2 rounded-2xl rounded-br-[4px] bg-ink px-4 py-3 text-white shadow-float sm:block" aria-hidden="true">
                <div class="mb-[3px] text-[11px] text-ink-300">CBT · SS 3 Mock Exam</div>
                <div class="text-sm leading-[1.35]">42 of 45 students submitted. Scores marked automatically.</div>
            </div>
        </div>
    </section>

    {{-- FIT STRIP --}}
    <section class="mx-auto max-w-[1200px] px-4 pt-6 sm:px-6" data-reveal>
        <div class="flex flex-wrap items-center justify-center gap-2.5">
            <span class="mr-1.5 text-sm font-semibold text-muted">Works the way your school does:</span>
            @foreach ($fits as $fit)
                <span class="rounded-full border border-line bg-white px-3.5 py-2 text-sm font-medium">{{ $fit }}</span>
            @endforeach
        </div>
    </section>

    {{-- MODULES --}}
    <section id="modules" x-data="{ group: 'All' }" class="mx-auto flex max-w-[1200px] flex-col gap-9 px-4 pt-24 pb-14 sm:px-6">
        <div class="flex flex-col items-center gap-3.5 text-center" data-reveal>
            <div class="kicker">17 modules · one login</div>
            <h2 class="display-2 max-w-[800px]">Everything your school runs on, <span class="text-brand-600">without the Excel sheets and paper files.</span></h2>
        </div>

        @php
            $groupCounts = collect($modules)->countBy('group');
        @endphp
        <div class="flex flex-wrap justify-center gap-1.5 self-center rounded-[18px] border border-line bg-white p-1.5" role="group" aria-label="Filter modules">
            @foreach (['All' => count($modules), ...$groupCounts->all()] as $group => $count)
                <button
                    type="button"
                    @click="group = @js($group)"
                    :aria-pressed="(group === @js($group)).toString()"
                    :class="group === @js($group) ? 'bg-ink text-white' : 'text-muted hover:text-ink'"
                    class="flex items-center gap-2 rounded-xl px-4 py-2.5 text-[15px] font-semibold transition"
                >
                    {{ $group }}
                    <span
                        :class="group === @js($group) ? 'bg-white/20 text-white' : 'bg-chip text-muted'"
                        class="rounded-full px-2 py-0.5 text-xs"
                    >{{ $count }}</span>
                </button>
            @endforeach
        </div>

        <div class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,260px),1fr))] gap-3.5">
            @foreach ($modules as $module)
                @php($tone = $moduleGroups[$module['group']])
                <article
                    x-show="group === 'All' || group === @js($module['group'])"
                    x-transition.opacity.duration.200ms
                    class="flex flex-col gap-3 rounded-[20px] border border-line bg-white p-[22px] transition hover:border-brand"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="grid size-[34px] place-items-center rounded-[10px] {{ $tone['chip'] }}">
                            <x-dynamic-component :component="'lucide-'.$module['icon']" class="size-[18px]" aria-hidden="true" />
                        </span>
                        <span class="text-xs font-semibold {{ $tone['text'] }}">{{ $module['group'] }}</span>
                    </div>
                    <h3 class="m-0 text-[19px] leading-tight font-semibold tracking-[-0.015em]">{{ $module['name'] }}</h3>
                    <p class="m-0 text-[15px] leading-[1.55] text-muted">{{ $module['description'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    {{-- SPOTLIGHT --}}
    <section id="spotlight" x-data="{ spot: 0, answer: null }" class="mx-auto flex max-w-[1200px] flex-col gap-9 px-4 pt-10 pb-20 sm:px-6">
        <div class="flex flex-col items-center gap-3.5 text-center" data-reveal>
            <div class="kicker">A closer look</div>
            <h2 class="display-2 max-w-[760px]">The jobs that take up your term, done in minutes.</h2>
        </div>

        <div class="flex flex-wrap justify-center gap-2 self-center rounded-[18px] border border-line bg-white p-1.5" role="tablist" aria-label="Features">
            @foreach ($spotlight as $index => $spot)
                <button
                    type="button"
                    role="tab"
                    id="spot-tab-{{ $spot['key'] }}"
                    aria-controls="spot-panel-{{ $spot['key'] }}"
                    @click="spot = {{ $index }}"
                    :aria-selected="(spot === {{ $index }}).toString()"
                    :tabindex="spot === {{ $index }} ? 0 : -1"
                    :class="spot === {{ $index }} ? 'bg-ink text-white' : 'text-muted hover:text-ink'"
                    class="rounded-xl px-[18px] py-[11px] text-[15px] font-semibold transition"
                >{{ $spot['label'] }}</button>
            @endforeach
        </div>

        <div class="rounded-[28px] border border-line bg-white p-[clamp(24px,4vw,48px)]" data-reveal>
            @foreach ($spotlight as $index => $spot)
                <div
                    id="spot-panel-{{ $spot['key'] }}"
                    role="tabpanel"
                    aria-labelledby="spot-tab-{{ $spot['key'] }}"
                    x-show="spot === {{ $index }}"
                    @if ($index > 0) x-cloak @endif
                    class="grid grid-cols-1 items-center gap-6 lg:grid-cols-2"
                >
                    <div class="flex flex-col gap-[18px]">
                        <div class="text-sm font-semibold text-brand-600">{{ $spot['kicker'] }}</div>
                        <h3 class="m-0 text-[clamp(28px,3.2vw,38px)] leading-[1.1] font-bold tracking-[-0.03em] text-balance">{{ $spot['title'] }}</h3>
                        <p class="m-0 text-[17px] leading-relaxed text-pretty text-muted">{{ $spot['body'] }}</p>
                        <ul class="m-0 flex list-none flex-col gap-2.5 p-0">
                            @foreach ($spot['points'] as $point)
                                <li class="flex items-center gap-3 text-base font-medium">
                                    <span class="grid size-[22px] flex-none place-items-center rounded-[7px] bg-brand-50 text-brand-600" aria-hidden="true"><x-lucide-check class="size-3.5" stroke-width="3" /></span>
                                    {{ $point }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="flex min-h-[360px] items-center rounded-[22px] bg-brand-50 p-[clamp(16px,3vw,28px)]">
                        @include('marketing.partials.spotlight.'.$spot['key'])
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ROLES --}}
    <section id="roles" class="px-4 py-10">
        <div class="mx-auto flex max-w-[1232px] flex-col gap-12 rounded-4xl bg-ink px-[clamp(20px,5vw,64px)] py-[clamp(32px,6vw,80px)] text-white">
            <div class="flex flex-wrap items-end justify-between gap-6" data-reveal>
                <h2 class="display-2 max-w-[640px]">One system for <span class="text-brand-200">everyone in your school.</span></h2>
                <p class="m-0 max-w-[400px] text-[17px] leading-relaxed text-ink-300">The proprietor, principal, bursar, teachers and parents each get their own login and see only what their role allows.</p>
            </div>
            <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,250px),1fr))] gap-4">
                @foreach ($roles as $role)
                    <article class="flex flex-col gap-4 rounded-3xl border border-ink-600 bg-ink-800 p-[26px] transition hover:border-brand" data-reveal>
                        <span class="self-start rounded-full px-3 py-1.5 text-[13px] font-semibold text-ink {{ $role['tagClass'] }}">{{ $role['tag'] }}</span>
                        <h3 class="m-0 text-[23px] leading-tight font-semibold tracking-[-0.02em]">{{ $role['title'] }}</h3>
                        <p class="m-0 text-[15px] leading-relaxed text-ink-300">{{ $role['body'] }}</p>
                        <div class="mt-auto h-px bg-ink-600" aria-hidden="true"></div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($role['items'] as $item)
                                <span class="rounded-[9px] bg-ink-700 px-[11px] py-1.5 text-[13px] text-ink-200">{{ $item }}</span>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- HOW --}}
    <section id="how" class="mx-auto flex max-w-[1200px] flex-col gap-11 px-4 pt-24 pb-14 sm:px-6">
        <div class="flex flex-col gap-3.5" data-reveal>
            <div class="kicker">Switching is easy</div>
            <h2 class="display-2 max-w-[760px]">Ready before your next resumption.</h2>
        </div>
        <ol class="m-0 grid list-none grid-cols-[repeat(auto-fit,minmax(min(100%,260px),1fr))] gap-[18px] p-0">
            @foreach ($steps as $step)
                <li class="flex flex-col gap-3.5 rounded-3xl border border-line bg-white p-7" data-reveal>
                    <div class="flex items-center justify-between">
                        <span class="text-[56px] leading-none font-bold tracking-[-0.05em] text-brand-600">{{ $step['number'] }}</span>
                        <span class="rounded-full bg-chip px-[11px] py-[5px] text-[13px] font-semibold text-muted">{{ $step['time'] }}</span>
                    </div>
                    <h3 class="m-0 text-[22px] font-semibold tracking-[-0.02em]">{{ $step['title'] }}</h3>
                    <p class="m-0 text-base leading-relaxed text-muted">{{ $step['body'] }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- PRICING --}}
    <section id="pricing" class="mx-auto flex max-w-[1200px] flex-col gap-9 px-4 pt-14 pb-20 sm:px-6">
        <div class="flex flex-col items-center gap-3.5 text-center" data-reveal>
            <div class="kicker">Pricing</div>
            <h2 class="display-2 max-w-[760px]">Pay per student, per term, in Naira.</h2>
            <p class="m-0 max-w-[560px] text-[17px] leading-relaxed text-muted">No dollar charges and no surprise fees. Pay each term, or for the whole session.</p>
        </div>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,300px),1fr))] items-stretch gap-[18px]">
            @foreach ($plans as $plan)
                @php($featured = $plan['popular'])
                <article @class([
                    'flex flex-col gap-5 rounded-[26px] border p-[30px]',
                    'border-ink bg-ink text-white' => $featured,
                    'border-line bg-white text-ink' => ! $featured,
                ]) data-reveal>
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="m-0 text-xl font-semibold">{{ $plan['name'] }}</h3>
                        @if ($featured)
                            <span class="rounded-full bg-brand-600 px-2.5 py-[5px] text-xs font-semibold text-white">Most popular</span>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-baseline gap-1.5">
                        @if ($plan['price'])
                            <span class="text-[40px] font-bold tracking-tightest">{{ $plan['price'] }}</span>
                            <span class="text-sm">/ student / term</span>
                        @else
                            <span class="text-[34px] font-bold tracking-[-0.03em]">Get a quote</span>
                            <span @class(['text-sm', 'text-ink-300' => $featured, 'text-muted' => ! $featured])>priced per student, per term</span>
                        @endif
                    </div>
                    <p class="m-0 text-[15px] leading-[1.55]">{{ $plan['description'] }}</p>
                    <a
                        href="{{ $plan['cta'] === 'Contact sales' ? route('contact', ['kind' => 'question']) : '#cta' }}"
                        @class([
                            'rounded-xl p-[13px] text-center text-[15px] font-semibold transition',
                            'bg-brand-600 text-white hover:bg-brand-700' => $featured,
                            'bg-brand-50 text-brand-600 hover:bg-brand-100' => ! $featured && $plan['cta'] !== 'Contact sales',
                            'bg-ink text-white hover:bg-brand-600' => ! $featured && $plan['cta'] === 'Contact sales',
                        ])
                    >{{ $plan['cta'] }}</a>
                    <ul class="m-0 flex list-none flex-col gap-2.5 p-0 text-[15px]">
                        @foreach ($plan['features'] as $feature)
                            <li class="flex items-center gap-2.5">
                                <span class="grid size-5 flex-none place-items-center rounded-md bg-brand-50 text-brand-600" aria-hidden="true"><x-lucide-check class="size-3" stroke-width="3" /></span>
                                {{ $feature }}
                            </li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" x-data="{ open: 0 }" class="mx-auto grid max-w-[1200px] grid-cols-1 items-start gap-12 px-4 pt-10 pb-20 sm:px-6 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
        <div class="flex flex-col gap-3.5" data-reveal>
            <div class="kicker">Common questions</div>
            <h2 class="display-2">What proprietors usually ask us.</h2>
            <p class="m-0 text-[17px] leading-relaxed text-muted">Still have a question? <a href="{{ route('contact', ['kind' => 'question']) }}" class="font-medium text-brand-600 hover:text-ink">Talk to our team</a>.</p>
        </div>
        <div class="flex flex-col gap-2.5">
            @foreach ($faqs as $index => $faq)
                <div class="overflow-hidden rounded-[18px] border border-line bg-white">
                    <h3 class="m-0">
                        <button
                            type="button"
                            @click="open = open === {{ $index }} ? -1 : {{ $index }}"
                            :aria-expanded="(open === {{ $index }}).toString()"
                            aria-controls="faq-{{ $index }}"
                            class="flex w-full items-center justify-between gap-4 px-[22px] py-5 text-left text-lg font-semibold text-ink"
                        >
                            <span>{{ $faq['question'] }}</span>
                            <span
                                :class="open === {{ $index }} ? 'bg-brand-600 text-white' : 'bg-chip text-ink'"
                                class="grid size-[30px] flex-none place-items-center rounded-[10px] transition"
                                aria-hidden="true"
                            >
                                <x-lucide-plus class="size-4" x-show="open !== {{ $index }}" />
                                <x-lucide-minus class="size-4" x-show="open === {{ $index }}" x-cloak />
                            </span>
                        </button>
                    </h3>
                    <div id="faq-{{ $index }}" x-show="open === {{ $index }}" x-collapse @if ($index > 0) x-cloak @endif>
                        <p class="m-0 px-[22px] pb-[22px] text-base leading-[1.65] text-muted">{{ $faq['answer'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- CTA --}}
    <section id="cta" class="px-4 pb-10">
        <div class="relative mx-auto flex max-w-[1232px] flex-col items-center gap-[22px] overflow-hidden rounded-4xl bg-brand-600 px-6 py-[clamp(40px,7vw,96px)] text-center text-white">
            <div class="absolute -top-40 -left-40 size-[420px] rounded-full border-[60px] border-white/8" aria-hidden="true"></div>
            <div class="absolute -right-[110px] -bottom-[140px] size-[300px] rounded-full bg-white/14" aria-hidden="true"></div>

            <div class="relative text-[13px] font-semibold tracking-kicker" data-reveal>BEFORE NEXT RESUMPTION</div>
            <h2 class="relative m-0 max-w-[860px] text-[clamp(38px,5.6vw,72px)] leading-[1.02] font-bold tracking-tightest text-balance" data-reveal>Start the new term with results, fees and records in one place.</h2>
            <p class="relative m-0 max-w-[540px] text-lg leading-[1.55]">Book a free demo for your school. We’ll show you around, then move your students, classes and fees in for you.</p>
            <x-marketing.lead-form source="cta" :dark="true" class="relative items-center" />
        </div>
    </section>

</x-layouts.marketing>
