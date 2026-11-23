{{-- A student's or staff member's passport photo, or their initials until one is uploaded. Size it with classes. --}}
@props(['person'])

@php
    $url = $person->photoUrl();
    $tone = $person->gender === \App\Enums\Gender::Female ? 'bg-[#FFD6D2] text-ink' : 'bg-sky-100 text-ink';
@endphp

@if ($url)
    <img src="{{ $url }}" alt="" loading="lazy" {{ $attributes->class(['flex-none object-cover']) }}>
@else
    <span {{ $attributes->class(['grid flex-none place-items-center font-bold', $tone]) }} aria-hidden="true">{{ $person->initials() }}</span>
@endif
