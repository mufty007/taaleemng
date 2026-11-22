<x-layouts.app title="Enrolment report">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.reports.index') }}" class="hover:text-ink">Reports</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">Enrolment</span>
    </div>

    <x-page-header kicker="Reports" title="Enrolment by class" :description="($session?->name ?? 'No current session').' · '.$total.' students'">
        <x-slot:actions>
            <x-button data-native variant="secondary" icon="printer" type="button" x-data x-on:click="window.print()">Print</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        <table class="w-full min-w-[480px] border-collapse text-left text-[15px]">
            <thead>
                <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                    <th scope="col" class="px-5 py-3 font-semibold">Class</th>
                    <th scope="col" class="px-5 py-3 text-right font-semibold">Students</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($arms as $arm)
                    <tr class="border-t border-line">
                        <td class="px-5 py-3 font-semibold">{{ $arm->displayName() }}</td>
                        <td class="px-5 py-3 text-right">{{ $arm->enrolments_count }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t border-line">
                    <td class="px-5 py-3 font-semibold">Total</td>
                    <td class="px-5 py-3 text-right font-bold">{{ $total }}</td>
                </tr>
            </tfoot>
        </table>
    </x-card>
</x-layouts.app>
