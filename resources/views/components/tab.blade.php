@props(['name'])

<section
    id="panel-{{ $name }}"
    role="tabpanel"
    aria-labelledby="tab-{{ $name }}"
    x-show="tab === @js($name)"
    x-cloak
    tabindex="0"
    {{ $attributes->class(['focus:outline-none']) }}
>
    {{ $slot }}
</section>
