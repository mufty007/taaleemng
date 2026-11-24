<x-layouts.app title="Classes">
    @php
        $rows = $levelsBySection->flatten(1)->flatMap(fn ($level) => $level->arms);
    @endphp

    <x-page-header kicker="Academics" title="Classes" :description="$session ? $rows->count().' classes · sizes are for the '.$session->name.' session.' : 'Set up a session to start enrolling students into classes.'">
        @if ($canManage && $rows->isNotEmpty())
            <x-slot:actions>
                <x-button icon="plus" type="button" x-data x-on:click="$dispatch('open-drawer', 'add-arm')">{{ $singleClasses ? 'Add a class' : 'Add a class arm' }}</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if ($rows->isEmpty())
        <x-card>
            <x-empty-state icon="layers" title="No classes yet" body="Start with the standard Nigerian classes and subjects for your type of school. You can rename, add or remove anything afterwards.">
                @if ($canManage)
                    <form method="POST" action="{{ route('school.classes.defaults') }}">
                        @csrf
                        <x-button variant="brand" icon="sparkles">Set up classes and subjects for me</x-button>
                    </form>
                @endif
            </x-empty-state>
        </x-card>
    @else
        <div x-data="{ section: 'all' }" class="flex flex-col gap-4">
            <div class="-mx-1 overflow-x-auto px-1">
                <div class="inline-flex gap-1 rounded-2xl border border-line bg-white p-1.5" role="group" aria-label="Filter by section">
                    @foreach (['all' => 'All classes'] + $levelsBySection->keys()->mapWithKeys(fn ($section) => [$section => $section])->all() as $value => $label)
                        <button type="button" @click="section = @js($value)" :aria-pressed="(section === @js($value)).toString()"
                            :class="section === @js($value) ? 'bg-ink text-white' : 'text-muted hover:text-ink'"
                            class="rounded-xl px-4 py-2 text-sm font-semibold whitespace-nowrap transition">{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            <x-card :padding="false">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] border-collapse text-left text-[15px]">
                        <thead>
                            <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                                <th scope="col" class="px-5 py-3 font-semibold">Class</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Section</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Form teacher</th>
                                <th scope="col" class="px-3 py-3 text-right font-semibold">Students</th>
                                <th scope="col" class="px-3 py-3 text-right font-semibold">Subjects</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $arm)
                                @php($canOpen = $canSeeAll || in_array($arm->id, $myArmIds, true))
                                <tr x-show="section === 'all' || section === @js($arm->level->section->label())" class="border-t border-line hover:bg-canvas">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="grid h-9 min-w-12 place-items-center rounded-lg bg-brand-50 px-2 text-sm font-bold text-brand-600">{{ $arm->displayName() }}</span>
                                            <span class="min-w-0">
                                                <span class="block font-semibold">{{ $arm->level->name }} {{ $arm->name }}</span>
                                                @if ($arm->stream)
                                                    <span class="text-sm text-muted">{{ $arm->stream->name }}</span>
                                                @endif
                                            </span>
                                            @if (in_array($arm->id, $myArmIds, true))
                                                <x-badge tone="brand">Yours</x-badge>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-sm text-muted">{{ $arm->level->section->label() }}</td>
                                    <td class="px-3 py-3 text-sm">{!! $arm->formTeacher ? e($arm->formTeacher->fullName()) : '<span class="text-muted">Not assigned</span>' !!}</td>
                                    <td class="px-3 py-3 text-right font-semibold">{{ $arm->students_count }}</td>
                                    <td class="px-3 py-3 text-right">{{ $arm->class_subjects_count }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            @if ($canOpen)
                                                <a href="{{ route('school.classes.show', $arm) }}" class="grid size-9 place-items-center rounded-lg text-muted hover:bg-chip hover:text-ink" title="Open {{ $arm->displayName() }}" aria-label="Open {{ $arm->displayName() }}">
                                                    <x-lucide-eye class="size-[18px]" aria-hidden="true" />
                                                </a>
                                            @endif
                                            @if ($canManage)
                                                <button type="button" title="Edit {{ $arm->displayName() }}" aria-label="Edit {{ $arm->displayName() }}"
                                                    class="grid size-9 place-items-center rounded-lg text-muted hover:bg-chip hover:text-ink"
                                                    x-data
                                                    x-on:click="$dispatch('edit-arm', { action: @js(route('school.classes.update', $arm)), values: @js(['title' => $arm->displayName(), 'name' => $arm->name, 'stream_id' => $arm->stream_id, 'form_teacher_id' => $arm->form_teacher_id, 'capacity' => $arm->capacity]) })">
                                                    <x-lucide-pencil class="size-[18px]" aria-hidden="true" />
                                                </button>
                                                <form method="POST" action="{{ route('school.classes.destroy', $arm) }}" data-confirm="{{ 'Delete '.$arm->displayName().'? This only works if it has no students.' }}" data-confirm-title="Delete class?" data-confirm-action="Delete">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" title="Delete {{ $arm->displayName() }}" aria-label="Delete {{ $arm->displayName() }}" class="grid size-9 place-items-center rounded-lg text-muted hover:bg-danger-50 hover:text-danger">
                                                        <x-lucide-trash-2 class="size-[18px]" aria-hidden="true" />
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    @endif

    @if ($canManage)
        <x-drawer
            name="add-arm"
            :title="$singleClasses ? 'Add a class' : 'Add a class arm'"
            :description="$singleClasses ? 'Your school runs one class per level, so each level (or SSS stream) has one class. It gets the standard subjects.' : 'The new arm gets the standard subjects for its class. You can change them on the class page.'"
            :show="$errors->addArm->isNotEmpty()"
        >
            <form method="POST" action="{{ route('school.classes.store') }}" class="flex flex-col gap-4">
                @csrf
                <x-select-field label="Class" name="class_level_id" :options="$levelOptions" bag="addArm" required />
                @if ($singleClasses)
                    <p class="m-0 rounded-xl bg-canvas px-3 py-2.5 text-sm text-muted">Need JSS 1A and JSS 1B? Switch to “A, B, C arms” in <a href="{{ route('school.settings.edit') }}#classes" class="font-medium text-brand-600">Settings → Classes</a>.</p>
                @else
                    <x-field label="Arm name" name="name" bag="addArm" placeholder="B" hint="Usually a letter, e.g. B for JSS 2B." required />
                @endif
                @if ($streamOptions)
                    <x-select-field label="Stream (SSS only)" name="stream_id" :options="$streamOptions" placeholder="No stream" bag="addArm" />
                @endif
                <x-select-field label="Form teacher" name="form_teacher_id" :options="$teacherOptions" placeholder="Choose later" bag="addArm" />
                <x-button variant="brand" class="self-start">{{ $singleClasses ? 'Add class' : 'Add class arm' }}</x-button>
            </form>
        </x-drawer>

        {{-- One edit panel, filled by whichever row's pencil was clicked. --}}
        <div x-data="editPanel('arm', @js(['action' => '', 'values' => ['title' => 'Edit class']]))">
            <x-drawer name="arm" title="Edit class" title-expression="'Edit ' + values.title" :show="$errors->editArm->isNotEmpty()">
                <form method="POST" :action="action" class="flex flex-col gap-4">
                    @csrf
                    @method('PATCH')
                    @unless ($singleClasses)
                        <div class="flex flex-col gap-1.5">
                            <label for="edit-arm-name" class="text-sm font-semibold">Arm name</label>
                            <input id="edit-arm-name" name="name" x-model="values.name" required class="field">
                            @error('name', 'editArm')<p class="text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
                        </div>
                    @endunless
                    @error('stream_id', 'editArm')<p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
                    @if ($streamOptions)
                        <div class="flex flex-col gap-1.5">
                            <label for="edit-arm-stream" class="text-sm font-semibold">Stream</label>
                            <select id="edit-arm-stream" name="stream_id" x-model="values.stream_id" class="field">
                                <option value="">No stream</option>
                                @foreach ($streamOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                    @endif
                    <div class="flex flex-col gap-1.5">
                        <label for="edit-arm-teacher" class="text-sm font-semibold">Form teacher</label>
                        <select id="edit-arm-teacher" name="form_teacher_id" x-model="values.form_teacher_id" class="field">
                            <option value="">No form teacher</option>
                            @foreach ($teacherOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="edit-arm-capacity" class="text-sm font-semibold">Capacity</label>
                        <input id="edit-arm-capacity" name="capacity" type="number" min="1" max="200" x-model="values.capacity" placeholder="No limit" class="field">
                    </div>
                    <x-button variant="brand" class="self-start" icon="save">Save class</x-button>
                </form>
            </x-drawer>
        </div>
    @endif
</x-layouts.app>
