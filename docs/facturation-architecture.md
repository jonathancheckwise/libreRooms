# Facturation & paiements La Pépite — note d'architecture

> Statut : **conception validée (décisions 1–4)**, implémentation par phases.
> Dernière mise à jour : 2026-10-18.

## 1. Le modèle actuel (fait, pas supposé)

| Concept | Modèle | Rôle | Relié au billing ? |
|---|---|---|---|
| **Entité facturée** | `Contact` (`reservation.tenant`) | Personne **ou** organisation qui réserve et reçoit la facture. Porte nom/`entity_name`, email, `invoice_email`, **adresse** (street/zip/city). | **OUI — pivot du billing** |
| Compte de connexion | `User` | Login. Relié à 0..n `Contact` (pivot) et 0..n `Company`. | Non (optionnel) |
| « Entreprises » (écran) | `Company` | Regroupe des **comptes** pour l'accès aux salles. | **Non** (en friche, cf. §7) |
| Facture | `Invoice` (`reservation.invoice`) | number, amount, dates, paid_at, owner. | OUI |

**Chaque réservation a toujours un `tenant` (Contact)** — invité, compte, ou poussée par un admin. `booked_by_user_id` est **nullable** (invité / sans compte). Le prix est calculé par `PricingService` depuis la durée (indépendant du compte).

### Décisions verrouillées
1. **`Company` / « Entreprises » : laissé en friche** (sert juste à l'accès salles, non relié au billing). À revoir plus tard.
2. **Dédup** : contacts **actuels** = proposés puis **validés manuellement** ; **futurs** = **auto**, avec **pop-up/confirmation en cas de doute**.
3. **Adresse obligatoire** sur toute réservation (nécessaire à la QR-facture).
4. **On facture l'entité pour laquelle la résa est faite.** Si 2 personnes d'une même structure → **une seule entité facturée**, mais **chaque résa garde qui a réservé**.

## 2. Bug confirmé : contacts cloisonnés par admin → doublons

Cause (vérifiée) :
- À la création d'un contact, il est **rattaché au seul admin créateur** (pivot `contact_users`).
- La liste Contacts est en vue **« mine »** par défaut ; même en « tous », un **modérateur** ne voit que ses contacts + ceux ayant une résa dans **ses** salles. **Seul un global admin voit tout.**

→ Magaly crée *Campus Demokratie* (privé). Emmanuelle ne le voit pas → le recrée → **doublon**. (Reproduit sur la base de test : `Association Test` ×2, un par admin.)

Outil de détection livré : `php artisan pepite:contacts-duplicates` (**lecture seule**, groupes par e-mail / nom d'organisation / nom de personne).

## 3. Cible « Entités »

- **Tous les admins (staff) voient TOUTES les entités** : avec compte, sans compte mais ayant déjà réservé, et celles créées par un admin pour pousser une résa. ⚠️ On élargit **l'accès staff uniquement** — un client normal continue de ne voir que ses propres contacts.
- **Dédup** = **fusion** (jamais suppression brute) : rebranche réservations + factures + liens users/owners sur le contact survivant, **sans perte d'historique**, de façon **auditable**.
- **Backfill** : fusionner les doublons existants **avant** de brancher bexio (sinon doublons côté bexio).
- **Infos facture obligatoires** sur résa sans compte : adresse + email de l'entité/la personne.

## 4. Facturation (modèle cible — à confirmer en détail)

- Au **1er du mois N+1** : **1 facture globale par entité** = somme des prestations du mois N.
- **Payable à 30 j**, délai **ajustable au cas par cas** (admin à la création / dans bexio / de vive voix). **Pas de relance automatique** stricte à J+30.
- **bexio = source de vérité comptable** (même un paiement Stripe y est réécrit « payé »).

## 5. Paiement (modèle cible — à confirmer en détail)

Après **confirmation** d'une résa, l'email contient 2 boutons :
- **Payer maintenant** → Stripe (CB en ligne).
- **Payer via QR-facture** → ajoute la presta à la **facture globale du mois**.

Règles :
- Rien coché → **QR-facture** (défaut). Stripe échoue → **QR-facture**.
- Lien de paiement **expire le jour de la réservation**. Pas payé le lendemain → la presta est **incrémentée sur la facture globale**.

## 6. Modèle de données à ajouter (phases 2–3)

- `contacts.bexio_contact_id` (mapping entité ↔ contact bexio) — clé de réconciliation.
- `invoices.bexio_invoice_id` + `invoices.payment_method` (`stripe` | `qr`) + `invoices.payment_status` / `paid_at`.
- Table/colonnes pour le lien de paiement Stripe (token, expiration = date de résa, statut).
- Webhooks Stripe (paiement réussi/échoué) → met à jour l'`Invoice` et réécrit dans bexio.

## 7. En friche / plus tard
- Relier `Company` ↔ un Contact de facturation (coworkers résidents facturés).
- Cas « 2 personnes même structure » : nécessite une notion d'« entité » regroupant des personnes (dépend du point `Company` ci-dessus).
- Interface admin KPIs.

## 8. Plan par phases
1. **Socle « Entités »** *(aucune dépendance externe)* : accès staff partagé à toutes les entités + détection/fusion de doublons + fusion des doublons existants + adresse obligatoire.
2. **bexio** : mapping Contact↔contact bexio, facture mensuelle globale, statut payé.
3. **Paiement** : boutons email, Stripe « payer maintenant », QR-facture par défaut/fallback, expiration J+résa.
4. **KPIs admin**.

## 9. Points ouverts à trancher
- Dédup auto future : fusion directe sur e-mail identique, ou toujours une suggestion à valider ? (le pop-up « en cas de doute » couvre les cas incertains)
- Où règle-t-on en priorité le délai des 30 j (création de résa par l'admin vs bexio) ?
- Détail exact du déclenchement de l'incrémentation (J+1 après la date de résa) côté planifié (cron) vs webhook Stripe.
