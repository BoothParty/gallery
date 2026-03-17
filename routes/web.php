<?php

use App\Http\Controllers\PartyController;
use Illuminate\Support\Facades\Route;

Route::get('/parties/{party}', [PartyController::class, 'show']);
