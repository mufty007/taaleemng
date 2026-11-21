@php
    use App\Enums\ImportStatus;
    $problemCount = count($import->problems);
@endphp

<x-layouts.app :title="'Import · '.$import->file_name">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.students.index') }}" class="hover:text-ink">Students</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <a href="{{ route('school.students.import.create') }}" class="hover:text-ink">Import</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="truncate text-ink">{{ $import->file_name }}</span>
    </div>

    <x-page-header kicker="Student import" :title="$import->file_name" :description="'Uploaded '.$import->created_at->format('j M Y, g:ia').' by '.($import->user?->name ?? 'a former member').'.'">
        <x-slot:actions>
            <x-badge :tone="$import->status->tone()">{{ $import->status->label() }}</x-badge>
        </x-slot:actions>
    </x-page-header>

    @if ($import->status === ImportStatus::Checked)
        <x-card>
            <div class="flex flex-wrap items-center gap-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-xl bg-naija-50 text-naija-700"><x-lucide-circle-check-big class="size-5" aria-hidden="true" /></span>
                    <div><div class="text-2xl font-bold">{{ number_format($import->total_rows) }}</div><div class="text-sm text-muted">ready to import</div></div>
                </div>
                <div class="flex items-center gap-3">
                    <span @class(['grid size-11 place-items-center rounded-xl', 'bg-danger-50 text-danger' => $problemCount, 'bg-chip text-muted' => ! $problemCount])><x-lucide-circle-alert class="size-5" aria-hidden="true" /></span>
                    <div><div class="text-2xl font-bold">{{ number_format($problemCount) }}</div><div class="text-sm text-muted">{{ $problemCount === 1 ? 'row needs' : 'rows need' }} fixing</div></div>
                </div>
                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('school.students.import.destroy', $import) }}">
                        @csrf
                        @method('DELETE')
                        <x-button variant="ghost" icon="trash-2">Discard</x-button>
                    </form>
                    <x-button variant="secondary" icon="upload" :href="route('school.students.import.create')">Upload another file</x-button>
                    @if ($import->total_rows > 0)
                        <form method="POST" action="{{ route('school.students.import.start', $import) }}">
                            @csrf
                            <x-button variant="brand" icon="user-plus">Import {{ number_format($import->total_rows) }} {{ str('student')->plural($import->total_rows) }}</x-button>
                        </form>
                    @endif
                </div>
            </div>
            @if ($problemCount && $import->total_rows)
                <p class="m-0 mt-4 border-t border-line pt-3 text-sm text-muted">Rows with problems are left out. Fix them in your spreadsheet and upload just those rows afterwards.</p>
            @endif
        </x-card>
    @elseif ($import->status->isInProgress())
        <x-card>
            <div x-data="importProgress({ url: @js(route('school.students.import.status', $import)), initial: @js(['status' => $import->status->value, 'label' => $import->status->label(), 'percent' => $import->percentDone(), 'processed' => $import->processed_rows, 'total' => $import->total_rows]) })" class="flex flex-col gap-3" aria-live="polite">
                <div class="flex items-center justify-between gap-3">
                    <span class="flex items-center gap-2 font-semibold"><x-lucide-loader-circle class="size-5 animate-spin text-brand-600" aria-hidden="true" /> <span x-text="label">{{ $import->status->label() }}</span></span>
                    <span class="text-sm text-muted"><span x-text="processed">{{ $import->processed_rows }}</span> of <span x-text="total">{{ $import->total_rows }}</span></span>
                </div>
                <div class="h-2.5 overflow-hidden rounded-full bg-chip" role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="percent">
                    <div class="h-full rounded-full bg-brand-600 transition-all duration-500" :style="`width: ${percent}%`" style="width: {{ $import->percentDone() }}%"></div>
                </div>
                <p class="m-0 text-sm text-muted">You can leave this page; the import carries on. Each student and parent login takes a moment to set up securely.</p>
            </div>
        </x-card>
    @elseif ($import->status === ImportStatus::Completed)
        <x-card>
            <div class="flex flex-wrap items-center gap-4">
                <span class="grid size-11 place-items-center rounded-xl bg-naija-50 text-naija-700"><x-lucide-circle-check-big class="size-5" aria-hidden="true" /></span>
                <div class="min-w-0 flex-1">
                    <div class="text-lg font-bold">{{ number_format($import->created_count) }} {{ str('student')->plural($import->created_count) }} enrolled</div>
                    <div class="text-sm text-muted">Finished {{ $import->finished_at?->format('j M Y, g:ia') }}.{{ $problemCount ? ' '.$problemCount.' '.str('row')->plural($problemCount).' left out; see Problems.' : '' }}</div>
                </div>
                @if ($import->credentials)
                    <x-button data-native variant="brand" icon="download" :href="route('school.students.import.credentials', $import)">Download logins (CSV)</x-button>
                @else
                    <x-badge>Logins downloaded</x-badge>
                @endif
            </div>
            @if ($import->credentials)
                <p class="m-0 mt-4 border-t border-line pt-3 text-sm text-muted">The file has each student’s and new parent’s temporary password. It can be downloaded <b>once</b>, then the passwords are deleted from Ta’aleem. Families can change them after signing in.</p>
            @endif
        </x-card>
    @else
        <div class="rounded-2xl bg-danger-50 px-5 py-4 text-[15px] text-danger" role="alert">
            <b>The import stopped after {{ $import->processed_rows }} of {{ $import->total_rows }} rows.</b> {{ $import->created_count }} students were enrolled. Check the students list before trying the remaining rows again.
        </div>
    @endif

    @php
        $tabs = array_filter([
            'ready' => $preview ? ['label' => 'Ready', 'count' => $import->total_rows] : null,
            'problems' => ['label' => 'Problems', 'count' => $problemCount],
        ]);
    @endphp

    <x-tabs :tabs="$tabs" :default="$preview ? 'ready' : 'problems'" label="Rows">
        @if ($preview)
            <x-tab name="ready">
                <x-card :padding="false">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] border-collapse text-left text-[15px]">
                            <thead>
                                <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                                    <th scope="col" class="w-16 px-5 py-3 font-semibold">Row</th>
                                    <th scope="col" class="px-3 py-3 font-semibold">Student</th>
                                    <th scope="col" class="px-3 py-3 font-semibold">Class</th>
                                    <th scope="col" class="px-3 py-3 font-semibold">Born</th>
                                    <th scope="col" class="px-3 py-3 font-semibold">Parent</th>
                                    <th scope="col" class="px-5 py-3 font-semibold">Phone</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($preview as $row)
                                    <tr class="border-t border-line">
                                        <td class="px-5 py-2.5 text-sm text-muted">{{ $row['line'] }}</td>
                                        <td class="px-3 py-2.5 font-medium">
                                            {{ str($row['student']['last_name'])->upper() }} {{ $row['student']['first_name'] }} {{ $row['student']['middle_name'] ?? '' }}
                                            <span class="block font-mono text-xs font-normal text-muted">{{ $row['student']['admission_no'] ?? 'Number given on import' }}</span>
                                        </td>
                                        <td class="px-3 py-2.5">{{ $classNames[$row['class_arm_id']] ?? '—' }}</td>
                                        <td class="px-3 py-2.5 text-sm text-muted">{{ isset($row['student']['date_of_birth']) ? \Illuminate\Support\Carbon::parse($row['student']['date_of_birth'])->format('d/m/Y') : '—' }}</td>
                                        <td class="px-3 py-2.5 text-sm">{{ trim(($row['guardian']['title'] ?? '').' '.$row['guardian']['first_name'].' '.$row['guardian']['last_name']) }}</td>
                                        <td class="px-5 py-2.5 font-mono text-sm">{{ $row['guardian']['phone'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($import->total_rows > count($preview))
                        <p class="m-0 border-t border-line px-5 py-3 text-sm text-muted">Showing the first {{ count($preview) }} of {{ number_format($import->total_rows) }}.</p>
                    @endif
                </x-card>
            </x-tab>
        @endif

        <x-tab name="problems">
            <x-card :padding="false">
                @if ($problemCount === 0)
                    <x-empty-state icon="circle-check-big" title="No problems" body="Every row passed the checks." />
                @else
                    <ul class="m-0 list-none divide-y divide-line p-0">
                        @foreach ($import->problems as $problem)
                            <li class="flex gap-4 px-5 py-3">
                                <span class="w-16 flex-none text-sm text-muted">Row {{ $problem['line'] }}</span>
                                <div class="min-w-0">
                                    <div class="font-medium">{{ $problem['name'] }}</div>
                                    <ul class="m-0 mt-1 list-disc pl-5 text-sm text-danger">
                                        @foreach ($problem['messages'] as $message)<li>{{ $message }}</li>@endforeach
                                    </ul>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </x-tab>
    </x-tabs>
</x-layouts.app>
