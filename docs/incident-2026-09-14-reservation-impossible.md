# Incident — « Impossible de réserver » (14.09.2026)

Retours multiples : impossible de réserver. Investigation menée en prod
(https://reservations.pepite-lausanne.ch) en tant que **visiteur non connecté**,
sur les 15 salles. Deux causes distinctes, indépendantes.

## Méthode
- Parcours public de `/rooms/{slug}/book` pour les 15 salles (destination réelle).
- Lecture de l'endpoint public `/rooms/{slug}/availability` (businessHours = day_start/day_end).
- Comparaison avec la config de référence `app/Console/Commands/ConfigureRooms.php`.

## Cause A — Salles « sur demande » : cul-de-sac vers /login (public bloqué)

Salles concernées : **La Big Room, La Place du Village, L'Atelier** (`on_request = true`).

- Sur la fiche publique, le bouton **« Demande spéciale »** pointe vers
  `/demande-speciale?room=…`.
- Cette route (`SpecialRequestController`) est derrière le middleware
  `['auth','verified']` (routes/web.php). Un visiteur y est **redirigé vers
  `/login`**, sans explication.
- `ReservationController@create` redirige de toute façon les invités des salles
  `on_request` vers `special-requests.create` → même mur de login.

Résultat : **aucun visiteur ne peut demander/réserver ces 3 salles** (les plus
demandées : Big Room, Place du Village). Vérifié : `/demande-speciale?room=6`
(Big Room) → 302 → `/login`.

Décision produit requise : le public doit-il pouvoir envoyer une demande/devis
sans compte, ou l'inscription est-elle volontairement obligatoire (mais alors le
bouton doit l'annoncer, pas jeter sur /login) ?

## Cause B — Créneau « soir » non réservable sur presque toutes les salles

`day_end_time` observé en prod (businessHours) :

| Salle | prod | attendu (ConfigureRooms) |
|---|---|---|
| La Coworking | 09:00–**21:00** | 09:00–21:00 ✓ |
| La Douce | 09:00–17:00 | 09:00–17:00 ✓ |
| La Dynamique | 09:00–**17:00** ❌ | 09:00–21:00 |
| La Grande Sérieuse | 09:00–**17:00** ❌ | 09:00–21:00 |
| La Petite Sérieuse | 09:00–**17:00** ❌ | 09:00–21:00 |
| La Focus | 09:00–**17:00** ❌ | 09:00–21:00 |
| La Chill | 09:00–**17:00** ❌ | 09:00–21:00 |
| La Secrète | 09:00–**17:00** ❌ | 09:00–21:00 |
| L'Accueil | 09:00–**17:00** ❌ | 09:00–21:00 |
| Cabine acoustique | 09:00–**17:00** ❌ | 09:00–21:00 |
| La Cuisine | 09:00–**17:00** ❌ | 09:00–21:00 |
| Big Room / Place / Atelier | 00:00–00:00 (on_request) | — |

13 salles sur 15 sont à **17:00**, une seule (Coworking) est restée à 21:00 →
signe d'une **modification de masse récente** du `day_end_time`.

Effet dans le formulaire : le choix de créneau propose (réglage global $pep,
identique pour toutes les salles) : matin 09–13, après-midi 13–17,
**soir 17–21**, journée 09–17, à l'heure. La fonction `isNonBookable()`
(resources/js/reservations/reservation-form.js) rejette tout créneau dont la fin
dépasse `day_end_time`. Donc **« Demi-journée soir » → toujours « Non réservable »**
sur les salles à 17:00 (= capture d'écran fournie), et l'horaire « à l'heure »
s'arrête à 16h.

Note : la capture montrant « Journée (09:00–17:00) → Occupé » n'est **pas** un bug :
il y a une vraie réservation ce jour-là (pastille « partiel » sur le 29).

Décision requise : l'horaire de fermeture réel est-il **21:00** (→ restaurer
day_end_time, le soir redevient réservable) ou **17:00** (→ retirer l'option
« soir » du formulaire pour ces salles) ? ⚠️ Ne PAS relancer `pepite:configure-rooms`
(écrase les réglages manuels des salles — cf. memory).

## Correctifs proposés (à valider avant déploiement = prod)
1. **Formulaire** : ne proposer que les créneaux qui tiennent dans la fenêtre
   d'ouverture réelle de la salle (masquer « soir » si fermeture < 21:00). Robuste
   quelle que soit la décision B — on n'affiche jamais un créneau non réservable.
2. **Salles sur demande** : rendre `/demande-speciale` accessible aux invités
   (ou, à minima, remplacer le renvoi brut vers /login par un message clair
   « créez un compte pour envoyer votre demande »).
3. **Config** : si fermeture = 21:00, restaurer `day_end_time` des salles listées
   (manuellement en base / admin, pas via configure-rooms).
4. **Logs** : ajouter un `Log::warning` quand un invité est renvoyé au login
   depuis une salle réservable/on_request, pour tracer ces cas.
