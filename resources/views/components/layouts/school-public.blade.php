{{-- A school's own public page (the admission form): the school's name up front, Ta'aleem in the footer. --}}
@props(['title', 'school'])

<x-layouts.base :title="$title.' · '.$school->name">
    <div class="flex min-h-screen flex-col bg-canvas">
        <header class="border-b border-line bg-white">
            <div class="mx-auto flex max-w-3xl items-center gap-4 px-4 py-5 sm:px-6">
                <x-school-crest :school="$school" class="size-12 flex-none rounded-2xl text-base" />
                <div class="min-w-0">
                    <div class="truncate text-lg font-bold tracking-[-0.02em]">{{ $school->name }}</div>
                    <div class="truncate text-sm text-muted">{{ $school->motto ?: trim(($school->lga ? $school->lga.', ' : '').($school->state ?? '')) }}</div>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-8 sm:px-6" data-enter>
            {{ $slot }}
        </main>

        <footer class="border-t border-line bg-white">
            <div class="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-3 px-4 py-4 text-sm text-muted sm:px-6">
                <span>{{ $school->address }}{{ $school->phone ? ' · '.\App\Models\User::displayPhone($school->phone) : '' }}</span>
                <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 hover:text-ink">Powered by <span class="font-semibold text-ink">Ta’aleem</span></a>
            </div>
        </footer>
    </div>
</x-layouts.base>
