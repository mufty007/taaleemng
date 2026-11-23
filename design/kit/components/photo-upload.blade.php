{{-- The person's photo with a camera button: choosing a picture uploads it straight away. --}}
@props(['person', 'action', 'name'])

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" x-data {{ $attributes->class(['group relative flex-none']) }}>
    @csrf
    <x-avatar :person="$person" class="size-20 rounded-3xl text-2xl" />
    <label class="absolute -right-1.5 -bottom-1.5 grid size-8 cursor-pointer place-items-center rounded-full border-2 border-white bg-brand-600 text-white shadow-card transition hover:bg-ink focus-within:ring-2 focus-within:ring-brand-200" title="{{ $person->photo_path ? 'Change photo' : 'Add a photo' }}">
        <x-lucide-camera class="size-4" aria-hidden="true" />
        <span class="sr-only">{{ $person->photo_path ? 'Change the photo of' : 'Add a photo of' }} {{ $name }}</span>
        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only" x-on:change="$el.files.length && $el.form.requestSubmit()">
    </label>
</form>
@error('photo')<p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
