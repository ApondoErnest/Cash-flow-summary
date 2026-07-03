<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? config('app.name') }} — {{ __('center.selection.title') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-flux.forced-light-appearance />
    </head>
    <body class="min-h-dvh font-sans text-text-body antialiased">
        {{ $slot }}

        @livewireScripts
        @fluxScripts
    </body>
</html>
