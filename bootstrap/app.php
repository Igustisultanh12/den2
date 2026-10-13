<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\LogVisitor;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\OfficeOnly; // Pastikan ini di-import

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        
        // 0. RADAR KEAMANAN TINGKAT TINGGI (HTTP REQUEST SMUGGLING, STREAM DoS, INJEKSI HEADER)
        $middleware->prepend(\App\Http\Middleware\SecurityFirewallMiddleware::class);

        // Pengalihan cerdas tamu belum login: pertahankan URL tujuan di query 'redirect'
        $middleware->redirectGuestsTo(fn (\Illuminate\Http\Request $request) => route('login', ['redirect' => $request->fullUrl()]));

        // 1. SOLUSI UTAMA TUNNELING & SSL
        // Menjaga agar aset (CSS/JS) tetap HTTPS meski di balik Cloudflare/Proxy
        $middleware->trustProxies(at: '*');

        // =====================================================================
        // SULTAN CONFIG: KECUALIKAN GERBANG MASUK FILE FISIK PESS DARI CSRF
        // =====================================================================
        $middleware->validateCsrfTokens(except: [
            'api/v1/pess/receive-submission',
            'login',
            'api/login',
            'api/mobile/*',
            'api/mobile/login',
            'api/mobile/config'
        ]);

        // 2. REGISTRASI SELURUH ALIAS MIDDLEWARE (Disatukan di sini)
        $middleware->alias([
            'role'        => RoleMiddleware::class,
            'office.only' => OfficeOnly::class, // Pagar Radar IP Kantor
            // Autentikasi token Sanctum + permission untuk seluruh /api/mobile/*
            'mobile.auth' => \App\Http\Middleware\MobileApiAuth::class,
        ]);

        // 3. KONFIGURASI STACK MIDDLEWARE WEB
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            
            /** * LogVisitor: Mencatat aktivitas Nama & NRP personel.
             * Diletakkan di stack WEB agar bisa membaca session login.
             */
            LogVisitor::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();