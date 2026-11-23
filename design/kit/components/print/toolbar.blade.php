{{-- The bar above a printable document. It never prints. --}}
@props(['width' => 'max-w-[860px]'])

<div class="no-print sticky top-0 z-10 border-b border-line bg-white/90 backdrop-blur">
    <div class="mx-auto flex {{ $width }} flex-wrap items-center justify-between gap-3 px-4 py-3">
        <a href="{{ url()->previous() }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:text-ink"><x-lucide-arrow-left class="size-4" aria-hidden="true" /> Back</a>
        <div class="flex flex-wrap items-center gap-2">
            {{ $slot }}
            <x-button icon="printer" type="button" onclick="window.print()">Print or save as PDF</x-button>
        </div>
    </div>
</div>
