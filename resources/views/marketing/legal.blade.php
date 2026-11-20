@php
    $title = $page === 'privacy' ? 'Privacy policy' : 'Terms of service';
@endphp

<x-layouts.marketing :title="$title">
    <section class="mx-auto flex max-w-[760px] flex-col gap-6 px-4 pt-16 pb-24 sm:px-6" data-enter>
        <div class="kicker">Legal</div>
        <h1 class="m-0 text-[clamp(36px,5vw,56px)] leading-[1.05] font-bold tracking-tightest">{{ $title }}</h1>
        <x-card class="border-gold-200 bg-gold-50">
            <p class="m-0 text-[15px] leading-relaxed text-gold">
                <b>Draft.</b> Our full {{ strtolower($title) }} is being finalised with our lawyers, in line with the Nigeria Data Protection Act 2023.
                Until it is published, email <a class="font-semibold underline" href="mailto:{{ config('taaleem.marketing.contact_email') }}">{{ config('taaleem.marketing.contact_email') }}</a> with any question about how we handle your school’s data.
            </p>
        </x-card>
        @if ($page === 'privacy')
            <div class="flex flex-col gap-4 text-[17px] leading-relaxed text-muted">
                <p class="m-0">In short: your school owns its data. We process it only to run Ta’aleem for you, we keep each school’s records separate from every other school’s, and we never sell personal information.</p>
                <p class="m-0">Information about children is collected by the school, with the consent of their parents or guardians, and is visible only to the people the school gives access to.</p>
            </div>
        @endif
    </section>
</x-layouts.marketing>
