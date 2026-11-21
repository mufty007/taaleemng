{{--
    A printable document: the school's letterhead, Ta'aleem blue accents and a
    "Powered by Ta'aleem" footer. Print it, or save it as a PDF from the print dialog.

    <x-layouts.print title="…" document="Official receipt" :school="$currentSchool" page="A5 landscape">
        <x-slot:meta>…right side of the letterhead…</x-slot:meta>
        …
        <x-slot:footer>…left side of the footer…</x-slot:footer>
    </x-layouts.print>
--}}
@props(['title', 'document', 'school', 'page' => 'A4', 'width' => 'max-w-[860px]'])

<x-layouts.base :title="$title">
    <x-slot:head>
        <style>@media print { @page { size: {{ $page }}; margin: 10mm; } }</style>
    </x-slot:head>

    <x-print.toolbar :width="$width">{{ $actions ?? '' }}</x-print.toolbar>

    <main {{ $attributes->class(['sheet relative mx-auto my-6 overflow-hidden rounded-2xl border border-line bg-white p-8 shadow-card', $width]) }}>
        <div class="mb-5 h-1.5 rounded-full bg-gradient-to-r from-brand-700 via-brand to-brand-200" aria-hidden="true"></div>

        <x-print.letterhead :school="$school" :document="$document">{{ $meta ?? '' }}</x-print.letterhead>

        {{ $slot }}

        <x-print.footer>{{ $footer ?? '' }}</x-print.footer>
    </main>
</x-layouts.base>
