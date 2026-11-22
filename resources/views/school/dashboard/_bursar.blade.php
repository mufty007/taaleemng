{{-- Bursar: money work on the left, actions and notices on the right. --}}
<div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
    <div class="flex min-w-0 flex-col gap-6">
        @if ($fees)
            @include('school.dashboard._fees')
        @endif
        @include('school.dashboard._payments')
        @include('school.dashboard._cashflow')
        @include('school.dashboard._activity')
    </div>
    <aside class="flex min-w-0 flex-col gap-6">
        <x-card>
            <h2 class="m-0 text-base font-semibold">Quick actions</h2>
            <div class="mt-3 grid grid-cols-1 gap-2">
                <x-button variant="secondary" icon="search" :href="route('school.students.index')" class="justify-start">Find a student to record a payment</x-button>
                <x-button variant="secondary" icon="receipt" :href="route('school.invoices.index', ['status' => 'unpaid'])" class="justify-start">Unpaid invoices</x-button>
                <x-button variant="secondary" icon="hand-coins" :href="route('school.debtors.index')" class="justify-start">Debtors list</x-button>
                @can('fees.manage')
                    <x-button variant="secondary" icon="settings-2" :href="route('school.fees.setup')" class="justify-start">Fee setup</x-button>
                @endcan
            </div>
        </x-card>
        @include('school.announcements._latest')
        @include('school.dashboard._term')
    </aside>
</div>
