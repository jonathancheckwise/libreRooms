# Notes de merge — heads-up entre branches en cours

## ⚠️ `resources/views/reservations/create.blade.php` — conflit probable

**Contexte (14.09.2026).** La PR **#45** (`fix/reservation-impossible-1409`,
mergée dans `main`, incident « impossible de réserver ») a modifié
`resources/views/reservations/create.blade.php` :

- ajout d'une fonction JS **`pepFilterModesByHours()`** (masque les créneaux
  fixes hors des horaires d'ouverture de la salle), insérée **juste avant**
  `pepPrefillDate()` ;
- ajout de l'appel **`pepFilterModesByHours();`** dans le
  `DOMContentLoaded`, entre `initHourly();` et `pepCalInit();`.

Les PR en cours **#42 (day-planning drag-select)** et **#43 (type d'événement
« Événement »)** touchent AUSSI ce fichier (bloc `<script>` et include
`event-info`). Au moment de rebaser/merger ces branches sur `main`, il y aura
**très probablement un petit conflit** dans ce fichier — autour du
`DOMContentLoaded` et des fonctions `pep*`.

**Rien n'est cassé** : c'est une résolution manuelle simple. Garder les DEUX
apports :
1. l'appel `pepFilterModesByHours();` (de #45) dans le `DOMContentLoaded` ;
2. les ajouts de #42/#43 (listeners day-planning, select `event_type`).

Détail de l'incident : `docs/incident-2026-09-14-reservation-impossible.md`.
