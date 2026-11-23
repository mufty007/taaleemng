<x-layouts.app :title="$school->name">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('platform.schools.index') }}" class="hover:text-ink">Schools</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="truncate text-ink">{{ $school->name }}</span>
    </div>

    {{-- Header: who the school is, and the three things you do from here. --}}
    <div class="flex flex-wrap items-center gap-4 rounded-3xl border border-line bg-white p-5 sm:p-6">
        <x-school-crest :school="$school" class="size-16 flex-none rounded-2xl text-xl" />
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="m-0 text-2xl leading-tight font-bold tracking-[-0.02em] sm:text-[28px]">{{ $school->name }}</h1>
                <x-badge :tone="$school->status->tone()">{{ $school->status->label() }}</x-badge>
            </div>
            <p class="m-0 mt-1 text-sm text-muted">
                <span class="font-mono">{{ $school->short_code }}</span> · {{ $school->school_type->label() }}{{ $school->state ? ' · '.$school->state : '' }} · joined {{ $school->created_at->format('j M Y') }}
                @if ($school->status === \App\Enums\SchoolStatus::Trial && $school->trial_ends_at)
                    · <span @class(['font-semibold', 'text-danger' => $school->trial_ends_at->lte(now()->addDays(3))])>trial {{ $school->trial_ends_at->isPast() ? 'ended' : 'ends' }} {{ $school->trial_ends_at->format('j M') }}</span>
                @endif
            </p>
        </div>
        <div class="flex w-full flex-wrap gap-2 sm:w-auto">
            <x-button variant="secondary" icon="eye" href="#people">View as…</x-button>
            <x-button variant="secondary" icon="pencil" href="#settings">Edit</x-button>
            <x-button :href="route('school.dashboard', $school)" icon="arrow-up-right">Open school</x-button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <x-tabs :tabs="['overview' => 'Overview', 'people' => ['label' => 'People', 'count' => number_format($usage['members'])], 'billing' => 'Billing', 'modules' => 'Modules', 'activity' => 'Activity', 'settings' => 'Settings']" label="School sections" class="min-w-0">
            <x-tab name="overview">@include('platform.schools._overview')</x-tab>
            <x-tab name="people">@include('platform.schools._people')</x-tab>
            <x-tab name="billing">@include('platform.schools._billing')</x-tab>
            <x-tab name="modules">@include('platform.schools._modules')</x-tab>
            <x-tab name="activity">@include('platform.schools._activity')</x-tab>
            <x-tab name="settings">@include('platform.schools._settings')</x-tab>
        </x-tabs>

        <aside class="min-w-0">@include('platform.schools._details')</aside>
    </div>
</x-layouts.app>
