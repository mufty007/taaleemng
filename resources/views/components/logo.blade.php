@props(['mark' => false])

@if ($mark)
    <img src="{{ asset('images/taaleem-mark.png') }}" alt="{{ config('app.name') }}" {{ $attributes->class(['block size-8 rounded-lg']) }}>
@else
    <img src="{{ asset('images/taaleem-logo.png') }}" alt="{{ config('app.name') }}" width="917" height="200" {{ $attributes->class(['block h-8 w-auto']) }}>
@endif
