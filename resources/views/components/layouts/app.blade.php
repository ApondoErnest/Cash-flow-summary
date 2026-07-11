<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        <x-layouts.favicons />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-flux.forced-light-appearance />
    </head>
    <body class="min-h-screen bg-app-bg font-sans text-text-body antialiased">
        <x-layouts.shell>
            {{ $slot }}
        </x-layouts.shell>

        @livewireScripts
        @fluxScripts
    </body>
</html>
