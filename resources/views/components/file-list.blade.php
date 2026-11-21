{{--
    Files on an assignment or a handed-in piece of work. $url is fn (int $index): string.
    With removable, each file gets a "remove" checkbox named remove_files[].
--}}
@props(['files', 'url', 'removable' => false])

@use('App\Support\Files\StoredFiles')

@if ($files)
    <ul {{ $attributes->class(['m-0 flex list-none flex-col gap-2 p-0']) }}>
        @foreach ($files as $index => $file)
            <li class="flex items-center gap-3 rounded-xl border border-line bg-white px-3 py-2.5">
                @if (StoredFiles::isImage($file))
                    <a href="{{ $url($index) }}" target="_blank" rel="noopener" class="flex-none"><img src="{{ $url($index) }}" alt="" loading="lazy" class="size-11 rounded-lg border border-line object-cover"></a>
                @else
                    <span class="grid size-11 flex-none place-items-center rounded-lg bg-brand-50 text-brand-600"><x-dynamic-component :component="'lucide-'.StoredFiles::icon($file)" class="size-5" aria-hidden="true" /></span>
                @endif
                <div class="min-w-0 flex-1">
                    <a href="{{ $url($index) }}" target="_blank" rel="noopener" class="block truncate text-[15px] font-medium hover:text-brand-600">{{ $file['name'] }}</a>
                    <div class="text-xs text-muted">{{ StoredFiles::size($file) }}</div>
                </div>
                @if ($removable)
                    <label class="flex items-center gap-1.5 text-sm text-muted"><input type="checkbox" name="remove_files[]" value="{{ $index }}" class="size-4 rounded border-line"> Remove</label>
                @else
                    <a href="{{ $url($index) }}?download=1" data-native class="grid size-9 flex-none place-items-center rounded-lg text-muted hover:bg-chip hover:text-ink" aria-label="Download {{ $file['name'] }}"><x-lucide-download class="size-4" aria-hidden="true" /></a>
                @endif
            </li>
        @endforeach
    </ul>
@endif
