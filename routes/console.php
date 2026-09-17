<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Les relances du livre d'or : l'annonce qui manque à J-7 de l'événement, et
 * les enregistrements qui expirent. Quotidien — la commande est idempotente,
 * un jour manqué est rattrapé au suivant.
 */
Schedule::command('audiobook:remind')->dailyAt('09:00');
