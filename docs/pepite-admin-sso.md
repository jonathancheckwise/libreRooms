# Lien avec le panneau admin du site (admin.pepite-lausanne.ch)

LibreRooms sert de **fournisseur d'identité** au panneau d'administration du
site vitrine : l'équipe n'a qu'un compte et qu'un mot de passe, ceux des
réservations. Seuls les comptes `is_global_admin` y ont accès.

Code : `app/Support/PepiteSso.php`, `app/Http/Controllers/PepiteSsoController.php`,
routes `pepite-sso.*` dans `routes/web.php`, commande `pepite:sso-secret`.
Côté site : `api/_lib/sso.php` (même format de jeton, à garder synchronisé).

## Les trois routes

| Route | Sens | Rôle |
|---|---|---|
| `GET /pepite-sso/authorize` | site → LibreRooms → site | Connexion au panneau. Si l'admin n'est pas connecté, LibreRooms montre SON formulaire, puis renvoie un jeton signé (id, nom, e-mail, `state`) à l'adresse de retour. Non-admin : retour avec `error=forbidden`. |
| `GET /pepite-sso/enter?token=` | site → LibreRooms | Bouton « Réservations » du panneau : ouvre la session sans redemander le mot de passe, puis va au planning. Jeton valable 60 s, **à usage unique** (nonce en cache). |
| `POST /pepite-sso/login` | serveur du site → LibreRooms | **Chemin utilisé depuis sept. 2026** : le formulaire est affiché sur admin.pepite-lausanne.ch, le site envoie e-mail + mot de passe (dans un jeton signé, usage unique) et reçoit l'identité. 5 essais/minute par e-mail. `authorize` reste disponible mais n'est plus utilisé. |
| `POST /pepite-sso/notify` | serveur du site → LibreRooms | Envoi d'un e-mail (nouvelle demande de modification) par le compte d'envoi déjà configuré ici. Exclue du CSRF (jeton signé à la place). |

Jeton : `base64url(json) . "." . base64url(hmac_sha256(base64url(json), PEPITE_SSO_SECRET))`,
avec `aud` (admin / reservations / notify) et `exp`.

## Mise en place (une fois, en SSH)

```bash
cd ~/sites/libreRooms && ./update.sh          # récupère le code
php artisan pepite:sso-secret                  # crée PEPITE_SSO_SECRET dans .env
php artisan optimize:clear && php artisan optimize
```

Le site lit ce secret **directement dans ce `.env`** (même hébergement,
chemin `../libreRooms/.env`). Si l'hébergement l'interdit, le recopier dans la
config du site (`sso_secret`, voir `docs/panneau-admin.md` du dépôt du site) :
`php artisan pepite:sso-secret --show`.

Adresses de retour autorisées : `PEPITE_SSO_REDIRECTS` (défaut
`https://admin.pepite-lausanne.ch/`, plusieurs préfixes séparés par des virgules).

## Changer le secret

`php artisan pepite:sso-secret --rotate` puis `optimize:clear && optimize`.
Les sessions déjà ouvertes dans le panneau restent valides ; seules les
nouvelles connexions utilisent le nouveau secret. Si le site a une copie du
secret dans sa config, la mettre à jour aussi.
