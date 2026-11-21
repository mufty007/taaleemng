<x-layouts.app title="Import students">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.students.index') }}" class="hover:text-ink">Students</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">Import</span>
    </div>

    <x-page-header kicker="People" title="Import students" description="Enrol a whole class, or the whole school, from a spreadsheet. Nothing is saved until you have seen what will be imported.">
        <x-slot:actions>
            <x-button variant="secondary" icon="download" :href="route('school.students.import.template')">Download template</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-tabs :tabs="['upload' => 'Upload', 'columns' => 'Columns', 'history' => ['label' => 'Past imports', 'count' => $imports->count()]]" label="Import">
        <x-tab name="upload">
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">
                <x-card>
                    <form method="POST" action="{{ route('school.students.import.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4">
                        @csrf
                        <div class="flex flex-col gap-1.5">
                            <label for="file" class="text-sm font-semibold">Student list (CSV)</label>
                            <input id="file" name="file" type="file" accept=".csv,text/csv" required @error('file') aria-invalid="true" aria-describedby="file-error" @enderror
                                class="block w-full rounded-xl border border-dashed border-line bg-canvas px-4 py-6 text-[15px] file:mr-4 file:rounded-lg file:border-0 file:bg-ink file:px-4 file:py-2 file:font-semibold file:text-white hover:border-brand">
                            @error('file')<p id="file-error" class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
                            <p class="m-0 text-[13px] text-muted">Up to {{ number_format(\App\Support\People\StudentListReader::MAX_ROWS) }} students and 2 MB. In Excel use <b>File › Save As › CSV (Comma delimited)</b>.</p>
                        </div>
                        <x-button variant="brand" icon="search-check" class="self-start">Check the file</x-button>
                    </form>
                </x-card>

                <x-card>
                    <h2 class="m-0 text-base font-semibold">How it works</h2>
                    <ol class="m-0 mt-3 flex list-none flex-col gap-3 p-0 text-[15px]">
                        @foreach ([
                            'Download the template and fill one row per student.',
                            'Upload it. We check every row and show you anything to fix.',
                            'Import the good rows. Each student and parent gets a login.',
                            'Download the logins to print and share with families.',
                        ] as $index => $step)
                            <li class="flex gap-3">
                                <span class="grid size-6 flex-none place-items-center rounded-full bg-brand-50 text-xs font-bold text-brand-600">{{ $index + 1 }}</span>
                                <span class="text-muted">{{ $step }}</span>
                            </li>
                        @endforeach
                    </ol>
                    <p class="m-0 mt-4 border-t border-line pt-3 text-[13px] text-muted">Brothers and sisters with the same parent phone number are linked to one parent login.</p>
                </x-card>
            </div>
        </x-tab>

        <x-tab name="columns">
            <x-card :padding="false">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] border-collapse text-left text-[15px]">
                        <thead>
                            <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                                <th scope="col" class="px-5 py-3 font-semibold">Column heading</th>
                                <th scope="col" class="px-3 py-3 font-semibold">What to put</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($columns as $column => $hint)
                                <tr class="border-t border-line">
                                    <td class="px-5 py-2.5 font-mono text-sm whitespace-nowrap">
                                        {{ $column }}
                                        @if (in_array($column, $required, true))<x-badge tone="brand" class="ml-1.5 font-sans">Required</x-badge>@endif
                                    </td>
                                    <td class="px-3 py-2.5 text-muted">{{ $hint }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-line px-5 py-4 text-sm">
                    <span class="font-semibold">Your classes:</span>
                    <span class="text-muted">{{ $classExamples->take(18)->join(', ') }}{{ $classExamples->count() > 18 ? ', …' : '' }}</span>
                </div>
            </x-card>
        </x-tab>

        <x-tab name="history">
            <x-card :padding="false">
                @if ($imports->isEmpty())
                    <x-empty-state icon="file-spreadsheet" title="No imports yet" body="Files you upload are listed here, with what was imported." />
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] border-collapse text-left text-[15px]">
                            <thead>
                                <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                                    <th scope="col" class="px-5 py-3 font-semibold">File</th>
                                    <th scope="col" class="px-3 py-3 font-semibold">Uploaded</th>
                                    <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                                    <th scope="col" class="px-3 py-3 text-right font-semibold">Enrolled</th>
                                    <th scope="col" class="px-5 py-3"><span class="sr-only">Open</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($imports as $import)
                                    <tr class="border-t border-line hover:bg-canvas">
                                        <td class="px-5 py-3 font-medium">{{ $import->file_name }}</td>
                                        <td class="px-3 py-3 text-sm text-muted">{{ $import->created_at->format('d/m/Y H:i') }} · {{ $import->user?->name ?? '—' }}</td>
                                        <td class="px-3 py-3"><x-badge :tone="$import->status->tone()">{{ $import->status->label() }}</x-badge></td>
                                        <td class="px-3 py-3 text-right">{{ $import->status === \App\Enums\ImportStatus::Completed ? $import->created_count : '—' }}</td>
                                        <td class="px-5 py-3 text-right"><a href="{{ route('school.students.import.show', $import) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-ink">Open <x-lucide-chevron-right class="size-4" aria-hidden="true" /></a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </x-tab>
    </x-tabs>
</x-layouts.app>
