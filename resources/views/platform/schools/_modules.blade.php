<x-card>
    <h2 class="m-0 text-base font-semibold">Modules</h2>
    <p class="m-0 mt-1 text-sm text-muted">What {{ $school->name }} can use. A module that’s off disappears from their menu, and its pages say it isn’t on their plan. Nothing is deleted.</p>
    <form method="POST" action="{{ route('platform.schools.modules', $school) }}" class="mt-5 flex flex-col gap-4">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach ($modules as $key => $module)
                <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-line p-4 transition has-checked:border-brand has-checked:bg-brand-50/40">
                    <input type="checkbox" name="modules[]" value="{{ $key }}" @checked($school->hasModule($key)) class="mt-1 size-4 rounded border-line text-brand-600">
                    <span class="min-w-0">
                        <span class="block font-semibold">{{ $module['label'] }}</span>
                        <span class="block text-sm text-muted">{{ $module['description'] }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        <x-button variant="brand" icon="check" class="self-start">Save modules</x-button>
    </form>
</x-card>
