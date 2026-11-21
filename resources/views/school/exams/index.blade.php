<x-layouts.app title="Exam timetable">
    <x-page-header kicker="Academics" title="Exam timetable" :description="$term->name.' '.$term->academicSession->name.' · '.$paperCount.' '.\Illuminate\Support\Str::plural('paper', $paperCount).($canManage ? '. Draft it in one go, then add venues and invigilators. No class sits two papers at once.' : '.')">
        <x-slot:actions>
            <form method="GET" class="flex gap-2">
                @if ($level)<input type="hidden" name="level" value="{{ $level->id }}">@endif
                <label for="term" class="sr-only">Term</label>
                <select id="term" name="term" class="field w-56 bg-white py-2" x-data x-on:change="$el.form.requestSubmit()">
                    @foreach ($terms as $id => $label)<option value="{{ $id }}" @selected($term->id === $id)>{{ $label }}</option>@endforeach
                </select>
            </form>
            @if ($paperCount)
                <x-button data-native variant="secondary" icon="printer" :href="route('school.exams.print', array_filter(['term' => $term->id, 'level' => $level?->id]))">Print</x-button>
            @endif
            @if ($canManage)
                <x-button variant="secondary" icon="wand-sparkles" type="button" x-data x-on:click="$dispatch('open-drawer', 'generate-exams')">Draft the timetable</x-button>
                <x-button variant="brand" icon="plus" type="button" x-data x-on:click="$dispatch('open-drawer', 'new-paper')">Add a paper</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <nav class="-mx-1 overflow-x-auto px-1" aria-label="Class">
        <div class="inline-flex gap-1 rounded-2xl border border-line bg-white p-1.5">
            <a href="{{ route('school.exams.index', ['term' => $term->id]) }}" @class(['rounded-xl px-3 py-1.5 text-sm font-semibold whitespace-nowrap', 'bg-ink text-white' => ! $level, 'text-muted hover:text-ink' => $level])>All classes</a>
            @foreach ($levels as $option)
                <a href="{{ route('school.exams.index', ['term' => $term->id, 'level' => $option->id]) }}" @class(['rounded-xl px-3 py-1.5 text-sm font-semibold whitespace-nowrap', 'bg-ink text-white' => $level?->is($option), 'text-muted hover:text-ink' => ! $level?->is($option)])>{{ $option->short_name ?? $option->name }}</a>
            @endforeach
        </div>
    </nav>

    @if ($days->isEmpty())
        <x-card>
            <x-empty-state icon="calendar-clock" title="No exam papers yet" :body="$canManage ? 'Draft the whole timetable from a start date, or add papers one at a time.' : 'The school hasn’t set the exam timetable for this term yet.'">
                @if ($canManage)
                    <x-button icon="wand-sparkles" type="button" x-data x-on:click="$dispatch('open-drawer', 'generate-exams')">Draft the timetable</x-button>
                @endif
            </x-empty-state>
        </x-card>
    @else
        <div class="flex flex-col gap-4">
            @foreach ($days as $date => $papers)
                <x-card>@include('school.exams._day')</x-card>
            @endforeach
        </div>
    @endif

    @if ($canManage)
        <x-drawer name="generate-exams" title="Draft the exam timetable" description="Each class sits one subject per sitting, English and Maths first, on weekdays from the start date. Papers already on the timetable stay where they are." :show="$errors->generate->isNotEmpty()" width="sm:max-w-lg">
            <form method="POST" action="{{ route('school.exams.generate') }}" class="flex flex-col gap-4" id="generate-exams-form" x-data="{ slots: ['08:30', '11:30'] }">
                @csrf
                <input type="hidden" name="term_id" value="{{ $term->id }}">
                <x-field label="First exam day" name="starts_on" type="date" bag="generate" :value="old('starts_on', $term->ends_on->copy()->subWeeks(2)->startOfWeek()->toDateString())" required />
                <fieldset class="m-0 flex flex-col gap-2 border-0 p-0">
                    <legend class="mb-1 text-sm font-semibold">Sittings each day</legend>
                    <template x-for="(slot, index) in slots" :key="index">
                        <div class="flex items-center gap-2">
                            <input type="time" name="slots[]" class="field w-36 py-2" x-model="slots[index]" required>
                            <button type="button" class="text-sm text-muted hover:text-danger" x-show="slots.length > 1" x-on:click="slots.splice(index, 1)">Remove</button>
                        </div>
                    </template>
                    <button type="button" class="self-start text-sm font-semibold text-brand-600" x-show="slots.length < 4" x-on:click="slots.push('13:30')">+ Add a sitting</button>
                    @error('slots.*', 'generate')<p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
                </fieldset>
                <fieldset class="m-0 flex flex-col gap-2 border-0 p-0">
                    <legend class="mb-1 text-sm font-semibold">Classes <span class="font-normal text-muted">(leave all unticked for every class)</span></legend>
                    <div class="grid grid-cols-3 gap-1.5">
                        @foreach ($levels as $option)
                            <label class="flex items-center gap-2 rounded-lg border border-line px-2.5 py-1.5 text-sm"><input type="checkbox" name="levels[]" value="{{ $option->id }}" class="size-4 accent-brand-600"> {{ $option->name }}</label>
                        @endforeach
                    </div>
                </fieldset>
            </form>
            <x-slot:footer><x-button variant="brand" icon="wand-sparkles" form="generate-exams-form">Draft it</x-button></x-slot:footer>
        </x-drawer>

        @foreach (['new-paper' => ['title' => 'Add a paper', 'action' => route('school.exams.store'), 'bag' => 'paper'], 'paper' => ['title' => 'Edit paper', 'action' => null, 'bag' => 'editPaper']] as $drawer => $config)
            <div x-data="editPanel(@js($drawer === 'paper' ? 'paper' : 'new-paper-unused'), { action: @js($config['action'] ?? ''), values: { class_level_id: @js((string) ($level?->id ?? '')), subject_id: '', date: @js($term->ends_on->copy()->subWeeks(2)->startOfWeek()->toDateString()), time: '08:30', duration_minutes: 90, venue: '', invigilator_id: '', notes: '' } })">
                <x-drawer :name="$drawer" :title="$config['title']" :show="$errors->{$config['bag']}->isNotEmpty()">
                    <form method="POST" :action="action" class="flex flex-col gap-4" id="{{ $drawer }}-form">
                        @csrf
                        @if ($drawer === 'paper') @method('PATCH') @endif
                        <input type="hidden" name="term_id" value="{{ $term->id }}">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1.5">
                                <label for="{{ $drawer }}-level" class="text-sm font-semibold">Class</label>
                                <select id="{{ $drawer }}-level" name="class_level_id" class="field" x-model="values.class_level_id" required>
                                    <option value="">Choose</option>
                                    @foreach ($levels as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="{{ $drawer }}-subject" class="text-sm font-semibold">Subject</label>
                                <select id="{{ $drawer }}-subject" name="subject_id" class="field" x-model="values.subject_id" required>
                                    <option value="">Choose</option>
                                    <template x-for="[id, name] in Object.entries(@js($subjectsByLevel)[values.class_level_id] ?? {})" :key="id">
                                        <option :value="id" x-text="name" :selected="id === values.subject_id"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-1 flex flex-col gap-1.5">
                                <label for="{{ $drawer }}-date" class="text-sm font-semibold">Date</label>
                                <input id="{{ $drawer }}-date" name="date" type="date" class="field" x-model="values.date" required>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="{{ $drawer }}-time" class="text-sm font-semibold">Starts</label>
                                <input id="{{ $drawer }}-time" name="time" type="time" class="field" x-model="values.time" required>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="{{ $drawer }}-duration" class="text-sm font-semibold">Minutes</label>
                                <input id="{{ $drawer }}-duration" name="duration_minutes" type="number" min="15" max="300" step="5" class="field" x-model="values.duration_minutes" required>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="{{ $drawer }}-venue" class="text-sm font-semibold">Venue</label>
                            <input id="{{ $drawer }}-venue" name="venue" class="field" maxlength="80" placeholder="e.g. Main hall, JSS 1A classroom" x-model="values.venue">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="{{ $drawer }}-invigilator" class="text-sm font-semibold">Invigilator</label>
                            <select id="{{ $drawer }}-invigilator" name="invigilator_id" class="field" x-model="values.invigilator_id">
                                <option value="">Not yet</option>
                                @foreach ($invigilators as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="{{ $drawer }}-notes" class="text-sm font-semibold">Note <span class="font-normal text-muted">(optional)</span></label>
                            <input id="{{ $drawer }}-notes" name="notes" class="field" maxlength="255" placeholder="e.g. Bring a calculator and mathematical set" x-model="values.notes">
                        </div>
                        @foreach (['time', 'invigilator_id', 'subject_id', 'class_level_id'] as $field)
                            @error($field, $config['bag'])<p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
                        @endforeach
                    </form>
                    <x-slot:footer><x-button variant="brand" icon="check" :form="$drawer.'-form'">{{ $drawer === 'paper' ? 'Save paper' : 'Add paper' }}</x-button></x-slot:footer>
                </x-drawer>
            </div>
        @endforeach
    @endif
</x-layouts.app>
