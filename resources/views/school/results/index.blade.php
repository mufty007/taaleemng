<x-layouts.app title="Results">
    <x-page-header kicker="Results" title="Results & broadsheets" description="Open a class to see its broadsheet, positions and report cards, and to release results to parents.">
        @if ($terms)
            <x-slot:actions>
                <form method="GET" class="flex items-center gap-2">
                    <label for="term" class="sr-only">Term</label>
                    <select id="term" name="term" class="field w-64 bg-white py-2" x-data x-on:change="$el.form.requestSubmit()">
                        @foreach ($terms as $id => $label)
                            <option value="{{ $id }}" @selected($term?->id === $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </x-slot:actions>
        @endif
    </x-page-header>

    <x-card :padding="false">
        @if ($arms->isEmpty() || ! $term)
            <x-empty-state icon="file-text" title="No classes to show" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] border-collapse text-left text-[15px]">
                    <thead>
                        <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                            <th scope="col" class="px-5 py-3 font-semibold">Class</th>
                            <th scope="col" class="px-3 py-3 text-right font-semibold">Students</th>
                            <th scope="col" class="px-3 py-3 text-right font-semibold">Comments written</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Parents can see results</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold"><span class="sr-only">Open</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($arms as $arm)
                            <tr class="border-t border-line hover:bg-canvas">
                                <td class="px-5 py-3 font-semibold">{{ $arm->displayName() }}</td>
                                <td class="px-3 py-3 text-right">{{ $arm->enrolments_count }}</td>
                                <td class="px-3 py-3 text-right">
                                    @php($written = (int) ($remarked[$arm->id] ?? 0))
                                    <span @class(['font-semibold', 'text-naija' => $arm->enrolments_count > 0 && $written >= $arm->enrolments_count, 'text-muted' => $written === 0])>{{ $written }}/{{ $arm->enrolments_count }}</span>
                                </td>
                                <td class="px-3 py-3">
                                    @if (isset($published[$arm->id]))
                                        <x-badge tone="naija">Published {{ \Illuminate\Support\Carbon::parse($published[$arm->id])->format('d/m/Y') }}</x-badge>
                                    @else
                                        <x-badge>Not yet</x-badge>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-2">
                                        @if ($remarkable->has($arm->id))
                                            <x-button variant="secondary" size="sm" icon="message-square-text" :href="route('school.remarks.edit', [$arm, 'term' => $term->id])">Remarks</x-button>
                                        @endif
                                        <x-button variant="soft" size="sm" icon="table-2" :href="route('school.results.show', [$arm, 'term' => $term->id])">Broadsheet</x-button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.app>
