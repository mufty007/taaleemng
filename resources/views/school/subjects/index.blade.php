<x-layouts.app title="Subjects">
    <x-page-header kicker="Academics" title="Subjects" :description="$subjects->count().' subjects. Use “Classes” on a row to choose which classes take it and who teaches it.'">
        <x-slot:actions>
            <x-button icon="plus" type="button" x-data x-on:click="$dispatch('open-drawer', 'add-subject')">Add a subject</x-button>
        </x-slot:actions>
    </x-page-header>

    <div x-data="{ search: '', category: 'all' }" class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative min-w-[220px] flex-1">
                <label for="subject-search" class="sr-only">Search subjects</label>
                <x-lucide-search class="pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 text-muted" aria-hidden="true" />
                <input id="subject-search" type="search" x-model="search" placeholder="Search by name or code" class="field bg-white pl-10">
            </div>
            <div class="-mx-1 overflow-x-auto px-1">
                <div class="inline-flex gap-1 rounded-2xl border border-line bg-white p-1.5" role="group" aria-label="Filter by type">
                    @foreach (['all' => 'All'] + $categories as $value => $label)
                        <button type="button" @click="category = @js($value)" :aria-pressed="(category === @js($value)).toString()"
                            :class="category === @js($value) ? 'bg-ink text-white' : 'text-muted hover:text-ink'"
                            class="rounded-xl px-3.5 py-2 text-sm font-semibold whitespace-nowrap transition">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </div>

        <x-card :padding="false">
            @if ($subjects->isEmpty())
                <x-empty-state icon="book-open" title="No subjects yet" body="Use “Set up classes and subjects for me” on the Classes page, or add subjects one by one." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] border-collapse text-left text-[15px]">
                        <thead>
                            <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                                <th scope="col" class="px-5 py-3 font-semibold">Subject</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Type</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Taught in</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Teachers</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subjects as $subject)
                                <tr x-show="(category === 'all' || category === @js($subject->category->value)) && (search === '' || @js(\Illuminate\Support\Str::lower($subject->name.' '.$subject->code)).includes(search.toLowerCase()))" class="border-t border-line hover:bg-canvas">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="grid h-8 min-w-12 place-items-center rounded-lg bg-brand-50 px-2 font-mono text-xs font-bold text-brand-600">{{ $subject->code }}</span>
                                            <span class="font-semibold">{{ $subject->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3"><x-badge>{{ $subject->category->label() }}</x-badge></td>
                                    <td class="px-3 py-3 text-sm">{{ $subject->class_subjects_count }} {{ \Illuminate\Support\Str::plural('class', $subject->class_subjects_count) }}</td>
                                    <td class="px-3 py-3">
                                        @if ($subject->class_subjects_count === 0)
                                            <span class="text-sm text-muted">—</span>
                                        @elseif ($subject->unassigned_count > 0)
                                            <x-badge tone="gold">{{ $subject->unassigned_count }} without a teacher</x-badge>
                                        @else
                                            <x-badge tone="naija">All assigned</x-badge>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <x-button variant="soft" size="sm" icon="list-checks" type="button" x-data
                                                x-on:click="$dispatch('edit-subject-classes', { action: {{ \Illuminate\Support\Js::from(route('school.subjects.classes', $subject)) }}, values: { title: {{ \Illuminate\Support\Js::from($subject->name) }}, assigned: {{ \Illuminate\Support\Js::from((object) ($assignments[$subject->id] ?? collect())->all()) }} } })">Classes</x-button>
                                            <button type="button" title="Edit {{ $subject->name }}" aria-label="Edit {{ $subject->name }}" class="grid size-9 place-items-center rounded-lg text-muted hover:bg-chip hover:text-ink" x-data
                                                x-on:click="$dispatch('edit-subject', { action: @js(route('school.subjects.update', $subject)), values: @js(['title' => $subject->name, 'name' => $subject->name, 'code' => $subject->code, 'category' => $subject->category->value]) })">
                                                <x-lucide-pencil class="size-[18px]" aria-hidden="true" />
                                            </button>
                                            <form method="POST" action="{{ route('school.subjects.destroy', $subject) }}" x-data x-on:submit="if (! confirm(@js('Delete '.$subject->name.'?'))) $event.preventDefault()">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="Delete {{ $subject->name }}" aria-label="Delete {{ $subject->name }}" class="grid size-9 place-items-center rounded-lg text-muted hover:bg-danger-50 hover:text-danger">
                                                    <x-lucide-trash-2 class="size-[18px]" aria-hidden="true" />
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>

    <x-drawer name="add-subject" title="Add a subject" :show="$errors->addSubject->isNotEmpty()">
        <form method="POST" action="{{ route('school.subjects.store') }}" class="flex flex-col gap-4">
            @csrf
            <x-field label="Subject name" name="name" bag="addSubject" placeholder="Yoruba" required />
            <x-field label="Short code" name="code" bag="addSubject" placeholder="YOR" hint="Used on broadsheets, up to 12 letters." required />
            <x-select-field label="Type" name="category" :options="$categories" bag="addSubject" required />
            <x-button variant="brand" class="self-start">Add subject</x-button>
        </form>
    </x-drawer>

    <div x-data="editPanel('subject', @js(['values' => ['title' => 'Edit subject']]))">
        <x-drawer name="subject" title="Edit subject" title-expression="'Edit ' + values.title" :show="$errors->editSubject->isNotEmpty()">
            <form method="POST" :action="action" class="flex flex-col gap-4">
                @csrf
                @method('PATCH')
                <div class="flex flex-col gap-1.5">
                    <label for="edit-subject-name" class="text-sm font-semibold">Subject name</label>
                    <input id="edit-subject-name" name="name" x-model="values.name" required class="field">
                    @error('name', 'editSubject')<p class="text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="edit-subject-code" class="text-sm font-semibold">Short code</label>
                    <input id="edit-subject-code" name="code" x-model="values.code" required class="field uppercase">
                    @error('code', 'editSubject')<p class="text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="edit-subject-category" class="text-sm font-semibold">Type</label>
                    <select id="edit-subject-category" name="category" x-model="values.category" class="field">
                        @foreach ($categories as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <x-button variant="brand" class="self-start" icon="save">Save subject</x-button>
            </form>
        </x-drawer>
    </div>

    {{-- Which classes take a subject, and their teachers. --}}
    <div x-data="editPanel('subject-classes', @js(['values' => ['title' => '', 'assigned' => (object) []]]))">
        <x-drawer name="subject-classes" title="Classes" title-expression="values.title + ': classes'" description="Tick each class that takes this subject and choose its teacher." width="sm:max-w-xl">
            <form method="POST" :action="action" class="flex flex-col gap-4">
                @csrf
                @method('PUT')
                <ul class="m-0 flex list-none flex-col divide-y divide-line rounded-2xl border border-line p-0">
                    @foreach ($arms as $arm)
                        <li class="flex flex-wrap items-center gap-3 px-4 py-2.5" x-data="{ get taught() { return Object.hasOwn(values.assigned, '{{ $arm['id'] }}') } }">
                            <input type="hidden" name="classes[{{ $arm['id'] }}][taught]" :value="taught ? 1 : 0">
                            <label class="flex min-w-0 flex-1 items-center gap-3 text-[15px]">
                                <input type="checkbox" class="size-4 accent-brand-600" :checked="taught"
                                    @change="$event.target.checked ? (values.assigned['{{ $arm['id'] }}'] = null) : (delete values.assigned['{{ $arm['id'] }}'])">
                                <span class="font-semibold">{{ $arm['name'] }}</span>
                                <span class="truncate text-sm text-muted">{{ $arm['stream'] ?? $arm['section'] }}</span>
                            </label>
                            <label for="teacher-{{ $arm['id'] }}" class="sr-only">Teacher for {{ $arm['name'] }}</label>
                            <select id="teacher-{{ $arm['id'] }}" name="classes[{{ $arm['id'] }}][teacher_id]" x-show="taught" class="field w-52 py-1.5 text-sm"
                                x-effect="$el.value = values.assigned['{{ $arm['id'] }}'] ?? ''"
                                @change="values.assigned['{{ $arm['id'] }}'] = $event.target.value || null">
                                <option value="">No teacher yet</option>
                                @foreach ($teacherOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                            </select>
                        </li>
                    @endforeach
                </ul>
                <x-button variant="brand" class="sticky bottom-0 self-start" icon="save">Save classes</x-button>
            </form>
        </x-drawer>
    </div>
</x-layouts.app>
