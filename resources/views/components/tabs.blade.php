{{--
    Tabs that follow the URL hash. Usage:
    <x-tabs :tabs="['overview' => 'Overview', 'parents' => 'Parents']">
        <x-tab name="overview">…</x-tab>
    </x-tabs>
    A tab label may be ['label' => 'Parents', 'count' => 2].
--}}
@props(['tabs', 'default' => null, 'label' => 'Sections'])

@php
    $names = array_keys($tabs);
    $default ??= $names[0];
@endphp

<div x-data="tabs(@js($names), @js($default))" {{ $attributes->class(['flex flex-col gap-5']) }}>
    {{-- On narrow screens the strip scrolls sideways; the edges fade to show there is more. --}}
    <div x-ref="strip" x-on:scroll.passive="measure()" x-on:resize.window.debounce.150ms="measure()" :style="edgeMask()" class="relative -mx-1 overflow-x-auto px-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <div class="inline-flex min-w-full gap-1 rounded-2xl border border-line bg-white p-1.5 sm:min-w-0" role="tablist" aria-label="{{ $label }}" @keydown.right.prevent="move(1)" @keydown.left.prevent="move(-1)">
            @foreach ($tabs as $name => $tab)
                @php($text = is_array($tab) ? $tab['label'] : $tab)
                <button
                    type="button"
                    role="tab"
                    id="tab-{{ $name }}"
                    data-tab="{{ $name }}"
                    aria-controls="panel-{{ $name }}"
                    @click="select(@js($name))"
                    :aria-selected="(tab === @js($name)).toString()"
                    :tabindex="tab === @js($name) ? 0 : -1"
                    :class="tab === @js($name) ? 'bg-ink text-white' : 'text-muted hover:text-ink'"
                    class="flex flex-none items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold whitespace-nowrap transition"
                >
                    {{ $text }}
                    @if (is_array($tab) && isset($tab['count']))
                        <span :class="tab === @js($name) ? 'bg-white/20 text-white' : 'bg-chip text-muted'" class="rounded-full px-1.5 text-xs">{{ $tab['count'] }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    {{ $slot }}
</div>
