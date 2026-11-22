<x-layouts.app title="Bell schedule">
    <x-page-header kicker="Timetable" title="Bell schedule" description="The periods and breaks of a school day. Every class’s timetable uses these times.">
        <x-slot:actions>
            <x-button icon="plus" type="button" x-data x-on:click="$dispatch('open-drawer', 'add-period')">Add a period</x-button>
        </x-slot:actions>
    </x-page-header>

    @include('school.timetable._tabs', ['current' => 'periods'])

    <x-card :padding="false" class="max-w-3xl">
        @if ($periods->isEmpty())
            <x-empty-state icon="clock" title="No periods yet" body="Start from a standard Nigerian school day and change the times to match your bell.">
                <form method="POST" action="{{ route('school.timetable.periods.defaults') }}">
                    @csrf
                    <x-button icon="sparkles">Use a standard school day</x-button>
                </form>
            </x-empty-state>
        @else
            <table class="w-full border-collapse text-left text-[15px]">
                <thead>
                    <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                        <th scope="col" class="px-5 py-3">Time</th>
                        <th scope="col" class="px-3 py-3">Name</th>
                        <th scope="col" class="px-3 py-3">Kind</th>
                        <th scope="col" class="px-5 py-3 text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($periods as $period)
                        <tr @class(['border-t border-line', 'bg-chip/60' => ! $period->kind->isLesson()])>
                            <td class="px-5 py-3 font-mono text-sm">{{ $period->times() }}</td>
                            <td class="px-3 py-3 font-medium">{{ $period->name }}</td>
                            <td class="px-3 py-3"><x-badge :tone="$period->kind->isLesson() ? 'brand' : 'neutral'">{{ $period->kind->label() }}</x-badge></td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-1">
                                    <x-button variant="ghost" size="sm" icon="pencil" type="button" x-data x-on:click="$dispatch('edit-period', { action: {{ \Illuminate\Support\Js::from(route('school.timetable.periods.update', $period)) }}, values: {{ \Illuminate\Support\Js::from(['name' => $period->name, 'starts_at' => substr($period->starts_at, 0, 5), 'ends_at' => substr($period->ends_at, 0, 5), 'kind' => $period->kind->value]) }} })">Edit</x-button>
                                    <form method="POST" action="{{ route('school.timetable.periods.destroy', $period) }}" data-confirm="{{ 'Remove '.$period->name.'? Lessons in it are removed from every class.' }}" data-confirm-title="Remove period?" data-confirm-action="Remove">
                                        @csrf
                                        @method('DELETE')
                                        <x-button variant="ghost" size="sm" icon="trash-2">Remove</x-button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>

    <x-drawer name="add-period" title="Add a period" :show="$errors->addPeriod->isNotEmpty()">
        <form method="POST" action="{{ route('school.timetable.periods.store') }}" class="flex flex-col gap-4">
            @csrf
            <x-field label="Name" name="name" bag="addPeriod" placeholder="e.g. Period 9, Lunch" required />
            <div class="grid grid-cols-2 gap-4">
                <x-field label="Starts" name="starts_at" type="time" bag="addPeriod" required />
                <x-field label="Ends" name="ends_at" type="time" bag="addPeriod" required />
            </div>
            <x-select-field label="Kind" name="kind" :options="$kinds" value="lesson" bag="addPeriod" required />
            <x-button variant="brand" class="self-start">Add period</x-button>
        </form>
    </x-drawer>

    <div x-data="editPanel('period')">
        <x-drawer name="period" title="Edit period">
            <form method="POST" :action="action" class="flex flex-col gap-4">
                @csrf
                @method('PATCH')
                <div class="flex flex-col gap-1.5">
                    <label for="edit-period-name" class="text-sm font-semibold">Name</label>
                    <input id="edit-period-name" name="name" class="field" x-model="values.name" required>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label for="edit-period-starts" class="text-sm font-semibold">Starts</label>
                        <input id="edit-period-starts" name="starts_at" type="time" class="field" x-model="values.starts_at" required>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="edit-period-ends" class="text-sm font-semibold">Ends</label>
                        <input id="edit-period-ends" name="ends_at" type="time" class="field" x-model="values.ends_at" required>
                    </div>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="edit-period-kind" class="text-sm font-semibold">Kind</label>
                    <select id="edit-period-kind" name="kind" class="field" x-model="values.kind">
                        @foreach ($kinds as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                    <p class="m-0 text-xs text-muted">Turning a lesson into a break removes its lessons from every class.</p>
                </div>
                <x-button variant="brand" class="self-start">Save</x-button>
            </form>
            @if ($errors->editPeriod->isNotEmpty())<p class="m-0 mt-3 text-[13px] font-medium text-danger">{{ $errors->editPeriod->first() }}</p>@endif
        </x-drawer>
    </div>
</x-layouts.app>
