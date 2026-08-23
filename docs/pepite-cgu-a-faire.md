# Conditions générales — ce qui reste à faire

Notes de suivi pour La Pépite. Référence : *Conditions générales de réservation
et d'utilisation des espaces*, date de révision du 23 août 2026.

## Fait

- Case d'acceptation obligatoire, jamais pré-cochée, refusée côté serveur.
- Impossible de cocher sans avoir ouvert les CG : le premier clic les ouvre dans
  un nouvel onglet, le clic suivant coche.
- Acceptation archivée sur la réservation : `terms_accepted_at`, `terms_version`.
  Couvre l'art. 1.7 avec l'identité, l'email et la référence déjà portés par la
  réservation et son contact. Pas d'adresse IP : les CG ne l'annoncent pas.
- Réglages `terms_version` et `terms_url`, renseignés par la migration
  `2026_08_23_100001`.

## À faire

### 1. Rappeler la version acceptée dans le courriel de confirmation

L'art. 1.5 : le courriel « rappelle, à titre informatif, la version des CG qui a
été acceptée lors de la demande ». Il ne s'agit **pas** de joindre le PDF, la
mention de la date de révision suffit.

La valeur est sur la réservation (`terms_version`). Ajouter la ligne au gabarit
du mail de confirmation, avec le lien vers le fichier daté correspondant :
`https://pepite-lausanne.ch/conditions-generales-AAAA-MM-JJ.pdf`.

**Bloqué par l'envoi de mail** : `config('mail.default')` vaut `log` et aucun
identifiant SMTP n'est renseigné, l'application n'envoie rien. Vérifiable sans
SMTP malgré tout : en mailer `log`, le message complet est écrit dans
`storage/logs/laravel.log`.

### 2. À chaque révision des CG

1. Publier le nouveau PDF **sous son nom daté** sur le site :
   `conditions-generales-AAAA-MM-JJ.pdf`.
2. Remplacer le fichier courant `conditions-generales.pdf`.
3. **Ne jamais écraser un fichier daté** : l'art. 18.2 engage La Pépite à
   archiver les versions antérieures avec leur période de validité.
4. Mettre `terms_version` à la date de révision imprimée en tête du document.

Le site publie deux copies du PDF, à la racine et sous `/apercu/`. Les mettre à
jour ensemble tant que les deux existent.

### 3. Divergence à trancher avec l'association

L'art. 1.8 retient la version « affichée sur le formulaire au moment où il·elle
coche la case ». L'art. 18.2 retient « celle publiée au jour de sa confirmation
écrite ». Les demandes passant en attente avant validation, les deux dates
peuvent différer. Le module suit l'art. 1.8. Si l'art. 18.2 doit primer, il
faudra enregistrer une seconde version au moment de la confirmation.
