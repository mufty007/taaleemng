@props(['icon' => 'inbox', 'title', 'body' => null])

<div {{ $attributes->class(['flex flex-col items-center gap-3 px-6 py-12 text-center']) }}>
    <span class="grid size-12 place-items-center rounded-2xl bg-brand-50 text-brand-600">
        <x-dynamic-component :component="'lucide-'.$icon" class="size-6" aria-hidden="true" />
    </span>
    <h3 class="m-0 text-lg font-semibold">{{ $title }}</h3>
    @if ($body)
        <p class="m-0 max-w-sm text-[15px] text-muted">{{ $body }}</p>
    @endif
    {{ $slot }}
</div>
