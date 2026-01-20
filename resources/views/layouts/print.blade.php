<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex nofollow">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.scss', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles

        @stack('styles')
    </head>
    <body class="font-sans antialiased">
        <x-banner />

        <div class="min-h-screen bg-white">

            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-white shadow">
                    <div class="py-2 mt-3">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>

        @stack('modals')

        @livewireScripts(['nonce' => csp_nonce()])

        @stack('scripts')
    </body>
</html>
