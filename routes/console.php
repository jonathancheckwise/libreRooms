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

// Facturation bexio INCRÉMENTALE : toutes les heures, ajoute aux brouillons
// mensuels (1 par entité et par mois) les réservations devenues facturables
// (passées, payantes, non déjà facturées). La facture du mois se construit donc
// au fil de l'eau dans bexio ; les admins la valident (brouillon). Ne facture
// que les résas ≥ date de bascule et non déjà facturées (aucune double facture).
Schedule::command('pepite:bexio-accrue')->hourly();
