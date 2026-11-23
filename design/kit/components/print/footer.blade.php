{{-- The foot of every printed document, with the Ta'aleem mark. --}}
<footer {{ $attributes->class(['mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-brand-200 pt-3 text-[11px] text-muted']) }}>
    <span>{{ $slot->isEmpty() ? 'Printed '.now()->format('j M Y, g:ia') : $slot }}</span>
    <span class="flex items-center gap-1.5">
        Powered by
        <img src="{{ asset('images/taaleem-logo.png') }}" alt="{{ config('app.name') }}" class="inline-block h-3.5 w-auto">
    </span>
</footer>
