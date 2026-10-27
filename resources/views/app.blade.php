<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $settings = \App\Models\Setting::pluck('value', 'key')->toArray();
            $agencyName = $settings['agency_name'] ?? 'DENINTEL KOARMADA 2';
            $faviconPath = $settings['favicon'] ?? ($settings['agency_logo'] ?? null);
            $faviconUrl = $faviconPath ? asset('storage/' . $faviconPath) : asset('favicon.ico');
        @endphp

        <title inertia>{{ config('app.name', 'SINDEN 2 - ' . $agencyName) }}</title>

        <!-- Dynamic Favicon Web Instansi -->
        <link rel="icon" type="image/x-icon" href="{{ $faviconUrl }}">
        <link rel="shortcut icon" type="image/x-icon" href="{{ $faviconUrl }}">
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,800,900&display=swap" rel="stylesheet" />

        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead

        <style>
            /* Smooth scrolling untuk kenyamanan di HP */
            html {
                scroll-behavior: smooth;
            }
            body {
                /* Mencegah overscroll bounce di iOS */
                overscroll-behavior-y: none;
                background-color: #f3f4f6;
            }
        </style>
    </head>
    <body class="font-sans antialiased text-gray-900 selection:bg-indigo-500 selection:text-white">
        @inertia
    </body>
</html>