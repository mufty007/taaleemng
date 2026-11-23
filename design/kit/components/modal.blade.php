{{--
    Open with: x-on:click="$dispatch('open-modal', 'name')"
    Pass :show="true" to reopen it after a failed submit (e.g. when its error bag has errors).
--}}
@props(['name', 'title', 'description' => null, 'show' => false, 'width' => 'max-w-lg'])

<div
    x-data="{ open: @js((bool) $show) }"
    x-on:open-modal.window="if ($event.detail === @js($name)) open = true"
    x-on:close-modal.window="if ($event.detail === @js($name)) open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $name }}-title"
>
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-ink/40" x-on:click="open = false" aria-hidden="true"></div>

    <div
        x-show="open"
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="translate-y-4 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-trap.noscroll="open"
        class="relative max-h-[92vh] w-full {{ $width }} overflow-y-auto rounded-t-3xl bg-white p-6 shadow-panel sm:rounded-3xl"
    >
        <div class="mb-5 flex items-start justify-between gap-4">
            <div>
                <h2 id="{{ $name }}-title" class="m-0 text-xl font-bold tracking-[-0.02em]">{{ $title }}</h2>
                @if ($description)
                    <p class="m-0 mt-1 text-sm text-muted">{{ $description }}</p>
                @endif
            </div>
            <button type="button" x-on:click="open = false" class="grid size-9 flex-none place-items-center rounded-lg text-muted hover:bg-chip hover:text-ink" aria-label="Close">
                <x-lucide-x class="size-5" aria-hidden="true" />
            </button>
        </div>
        {{ $slot }}
    </div>
</div>
