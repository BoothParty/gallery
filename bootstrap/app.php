<?php

use App\Http\Middleware\EnsureAudioGuestbook;
use App\Http\Middleware\EnsurePartyHost;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Le conteneur n'est jamais joint directement : le port 80 n'est pas
        // publié, seul le kamal-proxy du même réseau Docker parle à l'app. Sans
        // ça, Laravel ignore X-Forwarded-Proto et génère des URL absolues en
        // http:// sur une page servie en https:// (assets bloqués en contenu
        // mixte, redirections qui sortent du HTTPS).
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'party.host' => EnsurePartyHost::class,
            'audiobook' => EnsureAudioGuestbook::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
