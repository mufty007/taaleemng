<x-layouts.base title="School unavailable">
    <main class="grid min-h-screen place-items-center px-4 py-10">
        <div class="flex w-full max-w-lg flex-col items-center gap-5 text-center">
            <x-logo />
            <span class="grid size-14 place-items-center rounded-2xl bg-gold-50 text-gold">
                <x-lucide-lock class="size-7" aria-hidden="true" />
            </span>
            <h1 class="m-0 text-3xl font-bold tracking-[-0.03em]">{{ $school->name }} is not available right now</h1>
            <p class="m-0 text-[17px] leading-relaxed text-muted">
                This school’s account is {{ strtolower($school->status->label()) }}. Your records are safe. Please contact the school’s office, or reach the Ta’aleem team at
                <a class="font-semibold text-brand-600" href="mailto:{{ config('taaleem.marketing.contact_email') }}">{{ config('taaleem.marketing.contact_email') }}</a>.
            </p>
            <div class="flex flex-wrap justify-center gap-2">
                <x-button variant="secondary" :href="route('app')">Back to my schools</x-button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-button variant="ghost">Log out</x-button>
                </form>
            </div>
        </div>
    </main>
</x-layouts.base>
