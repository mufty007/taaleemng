@props([
    'label',
    'name',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'bag' => 'default',
    'id' => null,
])

@php
    $id ??= $name;
    $error = $errors->getBag($bag)->first($name);
    $describedBy = trim(($hint ? "{$id}-hint " : '').($error ? "{$id}-error" : ''));
@endphp

<div class="flex flex-col gap-1.5">
    <label for="{{ $id }}" class="text-sm font-semibold text-ink">{{ $label }}</label>
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        @if ($error) aria-invalid="true" @endif
        {{ $attributes->class(['field', 'field-error' => $error]) }}
    >
    @if ($hint)
        <p id="{{ $id }}-hint" class="text-[13px] text-muted">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $id }}-error" class="text-[13px] font-medium text-danger">{{ $error }}</p>
    @endif
</div>
