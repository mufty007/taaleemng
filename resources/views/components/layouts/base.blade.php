@props([
    'title' => null,
    'description' => 'School management for Nigerian nursery, primary, secondary and Islamiyya schools.',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="{{ $description }}">
        <meta name="theme-color" content="#101828">

        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/taaleem-mark.png') }}">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{ $head ?? '' }}
    </head>
    <body {{ $attributes->class(['min-h-screen bg-canvas text-ink antialiased']) }}>
        {{ $slot }}
    </body>
</html>
