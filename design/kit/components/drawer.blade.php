{{--
    Slide-in panel from the right, for editing without leaving the page.
    Open with: x-on:click="$dispatch('open-drawer', 'name')"
    Pass :show="true" to reopen it after a failed submit.
    `titleExpression` is an optional Alpine expression for a title that changes per row.
    An optional `footer` slot stays pinned below the scrolling content (for Save buttons).
--}}
@props(['name', 'title', 'description' => null, 'show' => false, 'width' => 'sm:max-w-md', 'titleExpression' => null])

<div
    x-data="{ open: @js((bool) $show) }"
    x-on:open-drawer.window="if ($event.detail === @js($name)) open = true"
    x-on:close-drawer.window="if ($event.detail === @js($name)) open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $name }}-drawer-title"
>
    <div x-show="open" x-transition.opacity.duration.200ms class="absolute inset-0 bg-ink/40" x-on:click="open = false" aria-hidden="true"></div>

    <div
        x-show="open"
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        x-trap.noscroll="open"
        @class(['absolute inset-y-0 right-0 flex w-full flex-col bg-white shadow-panel', $width])
    >
        <div class="flex items-start justify-between gap-4 border-b border-line px-6 py-5">
            <div class="min-w-0">
                <h2 id="{{ $name }}-drawer-title" class="m-0 truncate text-xl font-bold tracking-[-0.02em]" @if ($titleExpression) x-text="{{ $titleExpression }}" @endif>{{ $title }}</h2>
                @if ($description)
                    <p class="m-0 mt-1 text-sm text-muted">{{ $description }}</p>
                @endif
            </div>
            <button type="button" x-on:click="open = false" class="grid size-9 flex-none place-items-center rounded-lg text-muted hover:bg-chip hover:text-ink" aria-label="Close panel">
                <x-lucide-x class="size-5" aria-hidden="true" />
            </button>
        </div>
        <div class="flex-1 overflow-y-auto px-6 py-5">
            {{ $slot }}
        </div>
        @isset($footer)
            <div {{ $footer->attributes->class(['flex flex-wrap items-center justify-end gap-2 border-t border-line bg-white px-6 py-4']) }}>
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
