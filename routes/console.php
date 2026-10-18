<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the command to mark past reservations as finished
Schedule::command('reservations:finish-past')->hourly();

// Schedule the command to send late invoices reminders to owners
Schedule::command('invoices:send-late-reminders')->dailyAt('08:00');

// Schedule the command to merge duplicate contacts
Schedule::command('contacts:merge-identical --force')->dailyAt('03:00');

// Facturation mensuelle bexio : le 1er de chaque mois, crée les brouillons de
// factures globales (1 par entité) pour le mois précédent. Les admins valident
// (brouillons). Ne facture que les résas ≥ date de bascule et non déjà facturées.
Schedule::command('pepite:bexio-monthly-invoices --write')->monthlyOn(1, '06:00');
