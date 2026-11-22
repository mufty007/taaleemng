{{--
    One confirm dialog for the whole app shell. Forms opt in with data-confirm="…"
    (optional data-confirm-title, data-confirm-action, data-confirm-tone="danger|brand").
    Or dispatch: $dispatch('ask-confirm', { form, title, message, action, tone }).
--}}
<div
    x-data="confirmDialog"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-[60] flex items-end justify-center p-0 sm:items-center sm:p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="confirm-dialog-title"
>
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-ink/40" x-on:click="cancel()" aria-hidden="true"></div>

    <div
        x-show="open"
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="translate-y-4 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-trap.noscroll="open"
        class="relative w-full max-w-md rounded-t-3xl bg-white p-6 shadow-panel sm:rounded-3xl"
    >
        <h2 id="confirm-dialog-title" class="m-0 text-xl font-bold tracking-[-0.02em]" x-text="title"></h2>
        <p class="m-0 mt-2 text-[15px] text-muted" x-text="message"></p>
        <div class="mt-6 flex flex-wrap gap-2">
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-[15px] font-semibold whitespace-nowrap text-white transition"
                :class="tone === 'brand' ? 'bg-brand-600 hover:bg-ink' : 'bg-danger hover:bg-[#912018]'"
                x-on:click="proceed()"
                x-text="action"
            >Continue</button>
            <x-button type="button" variant="ghost" x-on:click="cancel()">Cancel</x-button>
        </div>
    </div>
</div>
