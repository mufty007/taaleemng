{{-- The school's uploaded logo, or its initials on a Ta'aleem-blue tile until one is uploaded. --}}
@props(['school'])

@if ($school->logoUrl())
    <img src="{{ $school->logoUrl() }}" alt="{{ $school->name }} logo" {{ $attributes->class(['block object-contain']) }}>
@else
    <span {{ $attributes->class(['grid place-items-center rounded-xl bg-brand-600 font-bold text-white']) }} aria-hidden="true">{{ $school->initials() }}</span>
@endif
