<x-layouts.marketing title="Contact">
    @php
        $sent = session('demo_sent') === 'contact';
        $whatsapp = config('taaleem.marketing.whatsapp_number');
        $channels = array_values(array_filter([
            ['icon' => 'mail', 'title' => 'Email us', 'sub' => config('taaleem.marketing.contact_email'), 'href' => 'mailto:'.config('taaleem.marketing.contact_email')],
            $whatsapp ? ['icon' => 'message-circle', 'title' => 'WhatsApp', 'sub' => 'Quick questions, quick answers', 'href' => 'https://wa.me/'.preg_replace('/\D+/', '', $whatsapp)] : null,
            ['icon' => 'calendar-days', 'title' => 'Book a 30-min demo', 'sub' => 'See Ta’aleem with your own classes', 'href' => '#form'],
        ]));
        $kindOptions = collect($kinds)->mapWithKeys(fn ($kind) => [$kind->value => [
            'label' => $kind->label(),
            'submit' => $kind->submitLabel(),
            'placeholder' => $kind->messagePlaceholder(),
        ]]);
    @endphp

    <section class="mx-auto grid max-w-[1200px] grid-cols-1 items-start gap-14 px-4 pt-14 pb-20 sm:px-6 sm:pt-[72px] lg:grid-cols-2">
        <div class="flex flex-col gap-6" data-enter>
            <div class="kicker">Contact</div>
            <h1 class="m-0 text-[clamp(42px,6vw,72px)] leading-[1.02] font-bold tracking-[-0.045em] text-balance">Let’s get your school <span class="text-brand-600">set up.</span></h1>
            <p class="m-0 max-w-[500px] text-[19px] leading-[1.55] text-pretty text-muted">Tell us a little about your school and we’ll get back to you within one working day, usually sooner, in shā’ Allāh.</p>

            <div class="mt-2 flex flex-col gap-3">
                @foreach ($channels as $channel)
                    <a href="{{ $channel['href'] }}" class="flex items-center gap-4 rounded-[18px] border border-line bg-white px-5 py-[18px] text-ink transition hover:border-brand">
                        <span class="grid size-11 flex-none place-items-center rounded-xl bg-brand-50 text-brand-600">
                            <x-dynamic-component :component="'lucide-'.$channel['icon']" class="size-5" aria-hidden="true" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[17px] font-semibold">{{ $channel['title'] }}</span>
                            <span class="block text-[15px] text-muted">{{ $channel['sub'] }}</span>
                        </span>
                        <x-lucide-arrow-right class="size-5 text-brand-600" aria-hidden="true" />
                    </a>
                @endforeach
            </div>

            <div class="flex items-center gap-2.5 text-sm text-muted">
                <span class="size-2 rounded-full bg-[#2EB67D]" aria-hidden="true"></span>
                We’re also around on weekends, when your school is.
            </div>
        </div>

        <div id="form" class="scroll-mt-24 rounded-[28px] border border-line bg-white p-[clamp(24px,4vw,40px)] shadow-[0_30px_60px_-30px_rgb(16_24_40/0.2)]">
            @if ($sent)
                <div class="flex flex-col items-center gap-4 px-2 py-12 text-center" role="status">
                    <span class="grid size-16 place-items-center rounded-[20px] bg-brand-600 text-white">
                        <x-lucide-check class="size-8" stroke-width="3" aria-hidden="true" />
                    </span>
                    <h2 class="m-0 text-[32px] font-bold tracking-[-0.03em]">JazākAllāhu khayran!</h2>
                    <p class="m-0 max-w-[360px] text-[17px] leading-relaxed text-muted">Your message is with our team. We’ll reply by email within one working day.</p>
                    <x-button variant="secondary" :href="route('contact')">Send another message</x-button>
                </div>
            @else
                <form
                    method="POST"
                    action="{{ route('demo-requests.store') }}"
                    x-data="{ kind: @js($selectedKind), size: @js(old('student_count', '50–150')), kinds: @js($kindOptions) }"
                    class="flex flex-col gap-[18px]"
                >
                    @csrf
                    <input type="hidden" name="source" value="contact">
                    <input type="hidden" name="kind" :value="kind" value="{{ $selectedKind }}">
                    <input type="hidden" name="student_count" :value="size" value="{{ old('student_count', '50–150') }}">
                    <div class="absolute -left-[9999px]" aria-hidden="true">
                        <label for="contact-website">Leave this empty</label>
                        <input id="contact-website" type="text" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="flex gap-1 rounded-[14px] border border-line bg-canvas p-[5px]" role="group" aria-label="What would you like?">
                        @foreach ($kindOptions as $value => $option)
                            <button
                                type="button"
                                @click="kind = @js($value)"
                                :aria-pressed="(kind === @js($value)).toString()"
                                :class="kind === @js($value) ? 'bg-ink text-white' : 'text-muted hover:text-ink'"
                                class="flex-1 rounded-[10px] px-1.5 py-2.5 text-[13px] font-semibold whitespace-nowrap transition sm:px-2 sm:text-sm"
                            >{{ $option['label'] }}</button>
                        @endforeach
                    </div>

                    <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                        <x-field label="Your name" name="name" bag="contact" placeholder="Fatima Ahmed" autocomplete="name" required />
                        <x-field label="Email" name="email" type="email" bag="contact" placeholder="you@yourschool.org" autocomplete="email" required />
                    </div>
                    <x-field label="School or masjid name" name="school_name" bag="contact" placeholder="Al-Noor Model School" autocomplete="organization" />

                    <fieldset class="m-0 flex flex-col gap-2.5 border-0 p-0">
                        <legend class="mb-2.5 text-sm font-semibold">How many students?</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($studentCounts as $count)
                                <button
                                    type="button"
                                    @click="size = @js($count)"
                                    :aria-pressed="(size === @js($count)).toString()"
                                    :class="size === @js($count) ? 'border-brand-600 bg-brand-50 text-brand-600' : 'border-line bg-white text-ink hover:border-brand'"
                                    class="rounded-[10px] border px-3.5 py-[9px] text-sm font-semibold transition"
                                >{{ $count }}</button>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="flex flex-col gap-1.5">
                        <label for="message" class="text-sm font-semibold">Message</label>
                        <textarea
                            id="message"
                            name="message"
                            rows="4"
                            :placeholder="kinds[kind].placeholder"
                            class="field resize-y"
                        >{{ old('message') }}</textarea>
                        @error('message', 'contact')
                            <p class="text-[13px] font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="rounded-[14px] bg-brand-600 p-[15px] text-[17px] font-semibold text-white transition hover:bg-ink" x-text="kinds[kind].submit">
                        {{ $kindOptions[$selectedKind]['submit'] ?? 'Send message' }}
                    </button>
                    <p class="m-0 text-center text-[13px] text-muted">We’ll never share your details.</p>
                </form>
            @endif
        </div>
    </section>
</x-layouts.marketing>
