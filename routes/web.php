<?php

use App\Http\Controllers\SocialiteController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PartyController;
use App\Http\Middleware\IsAdminUser;
use App\Models\Party;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('login', function () {
    return redirect()->route('auth.redirect', ['provider' => 'zoho']);
})->name('login');
Route::post('logout', function () {
    Auth::logout();

    return redirect()->route('home');
})->name('logout');

Route::get('/auth/{provider}/redirect', [SocialiteController::class, 'redirectToProvider'])
    ->where('provider', 'zoho')
    ->name('auth.redirect');
Route::get('/auth/{provider}/callback', [SocialiteController::class, 'handleProviderCallback'])
    ->where('provider', 'zoho')
    ->name('auth.callback');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('dashboard', [
            'user' => [
                'name' => Auth::user()->name,
                'email' => Auth::user()->email,
                'avatar' => null,
            ],
            'parties' => Auth::user()->parties()->get()->map(function ($party) {
                return [
                    'id' => $party->id,
                    'name' => $party->name,
                ];
            }),
        ]);
    })->name('dashboard')->middleware(IsAdminUser::class);

    Route::get('/users', [UserController::class, 'index'])
        ->name('users.index')
        ->middleware(IsAdminUser::class);

    Route::put('/users/{user}', [UserController::class, 'update'])
        ->name('users.update')
        ->middleware(IsAdminUser::class);

    Route::patch('/users/{user}/toggle-admin', [UserController::class, 'toggleAdmin'])
        ->name('users.toggle-admin')
        ->middleware(IsAdminUser::class);

    Route::delete('/users/{user}', [UserController::class, 'destroy'])
        ->name('users.destroy')
        ->middleware(IsAdminUser::class);

    Route::get('/parties', [PartyController::class, 'index'])
        ->name('parties.index')
        ->middleware(IsAdminUser::class);

    Route::put('/parties/{party}', [PartyController::class, 'update'])
        ->name('parties.update')
        ->middleware(IsAdminUser::class);

    Route::delete('/parties/{party}', [PartyController::class, 'destroy'])
        ->name('parties.destroy')
        ->middleware(IsAdminUser::class);

    Route::prefix('/parties/{party}')->group(function () {
        Route::get('/', function (Party $party) {
            return Inertia::render('dashboard/dashboard', [
                'user' => [
                    'name' => Auth::user()->name,
                    'email' => Auth::user()->email,
                    'avatar' => null,
                ],
                'parties' => Auth::user()->parties()->get()->map(function ($party) {
                    return [
                        'id' => $party->id,
                        'name' => $party->name,
                    ];
                }),
                'selected_party' => [
                    'id' => $party->id,
                    'name' => $party->name,
                ],
            ]);
        })->name('parties.show');
    });
});