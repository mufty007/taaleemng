{{-- Choose several files (papers, or photos straight from the phone camera) and see them listed before sending. --}}
@props(['name' => 'files', 'label' => 'Attach files', 'hint' => null, 'bag' => 'default'])

<div x-data="{ chosen: [] }" {{ $attributes->class(['flex flex-col gap-2']) }}>
    <label class="flex cursor-pointer flex-col items-center gap-1.5 rounded-2xl border-2 border-dashed border-brand-200 bg-brand-50/50 px-4 py-5 text-center transition hover:border-brand-600 hover:bg-brand-50 focus-within:border-brand-600">
        <x-lucide-upload class="size-6 text-brand-600" aria-hidden="true" />
        <span class="text-[15px] font-semibold text-brand-700">{{ $label }}</span>
        <span class="text-xs text-muted">{{ $hint ?? 'PDF, Word, PowerPoint or photos of your work · up to '.\App\Support\Files\StoredFiles::MAX_FILES.' files, 10 MB each' }}</span>
        <input type="file" name="{{ $name }}[]" multiple accept=".pdf,.doc,.docx,.odt,.rtf,.txt,.ppt,.pptx,.xls,.xlsx,image/*" class="sr-only" x-on:change="chosen = [...$event.target.files].map((file) => file.name + ' · ' + Math.max(1, Math.round(file.size / 1024)) + ' KB')">
    </label>
    <template x-if="chosen.length">
        <ul class="m-0 flex list-none flex-col gap-1 p-0 text-sm">
            <template x-for="file in chosen" :key="file">
                <li class="flex items-center gap-2 rounded-lg bg-chip px-3 py-1.5"><x-lucide-paperclip class="size-3.5 flex-none text-muted" aria-hidden="true" /><span class="truncate" x-text="file"></span></li>
            </template>
        </ul>
    </template>
    @error($name, $bag)<p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
    @error($name.'.*', $bag)<p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
</div>
