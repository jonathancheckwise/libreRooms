<?php

namespace App\Enums;

/**
 * Type d'activité d'une réservation à La Pépite (réunion, atelier, coworking,
 * événement…). Purement descriptif : n'influe pas sur le tarif, sert à qualifier
 * la demande et à afficher un intitulé clair (email de confirmation, admin).
 *
 * NB : à ne pas confondre avec le modèle ReservationEvent (les créneaux
 * horaires d'une réservation) — ici c'est la CATÉGORIE de la réservation.
 */
enum ReservationType: string
{
    case MEETING = 'meeting';
    case WORKSHOP = 'workshop';
    case COWORKING = 'coworking';
    case EVENT = 'event';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MEETING => __('Meeting'),
            self::WORKSHOP => __('Workshop / training'),
            self::COWORKING => __('Coworking'),
            self::EVENT => __('Event'),
            self::OTHER => __('Other'),
        };
    }
}
