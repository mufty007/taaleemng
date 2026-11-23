@props([
    'label',
    'name',
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'hint' => null,
    'bag' => 'default',
    'id' => null,
])

@php
    $id ??= $name;
    $error = $errors->getBag($bag)->first($name);
    $selected = (string) old($name, $value);
@endphp

<div class="flex flex-col gap-1.5">
    <label for="{{ $id }}" class="text-sm font-semibold text-ink">{{ $label }}</label>
    <div class="relative">
        <select
            id="{{ $id }}"
            name="{{ $name }}"
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->class(['field appearance-none pr-10', 'field-error' => $error]) }}
        >
            @if ($placeholder !== null)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>
        <x-lucide-chevron-down class="pointer-events-none absolute top-1/2 right-3.5 size-[18px] -translate-y-1/2 text-muted" aria-hidden="true" />
    </div>
    @if ($hint)
        <p class="text-[13px] text-muted">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $id }}-error" class="text-[13px] font-medium text-danger">{{ $error }}</p>
    @endif
</div>
