# Livre d'or audio

Date : 2026-09-17

## Contexte

Le studio loue aux clients un combiné téléphonique posé sur une table le jour de
l'événement. Les invités décrochent, entendent un message d'accueil enregistré
par le couple, puis laissent leur propre message après le bip. Le combiné
enregistre en MP3 sur sa carte mémoire.

Aujourd'hui tout ce parcours se fait à la main : le couple envoie son annonce
par WeTransfer ou par SMS, quelqu'un la convertit et la pose sur le combiné, et
après l'événement on renvoie les messages par un lien de partage. Rien n'est
tracé, personne ne sait où en est un dossier, et les relances se font de
mémoire.

L'application gère déjà l'autre produit du studio, la galerie photo : une
`Party` par événement, publiée sur un sous-domaine, alimentée par la borne
photo. Le livre d'or audio est un second produit vendu séparément ou avec le
premier.

## Objectif

Que le client enregistre son annonce depuis son navigateur, que le studio la
valide en un clic, et que les messages des invités reviennent au client sans
qu'aucune des deux parties n'ait à envoyer un fichier à la main.

## Périmètre

Dans le périmètre :

- les modules activables sur une `Party` (galerie photo, livre d'or audio) ;
- l'adressage par uuid, pour les événements sans sous-domaine ;
- l'espace client `/audiobook/{uuid}` : enregistrement de l'annonce, suivi de sa
  validation, écoute et récupération des messages des invités ;
- la file d'attente admin `/admin/livre-or` et la page par party ;
- le dépôt des messages par l'admin ;
- sept e-mails transactionnels et deux relances planifiées ;
- la conversion de l'annonce en MP3.

Hors périmètre :

- le partage de photos sans sous-domaine (`/pictures/{uuid}`). Seule la colonne
  `gallery_uuid` et la réservation du préfixe de route sont posées ici, pour ne
  pas avoir à remanier l'adressage une deuxième fois ; la surface elle-même
  viendra plus tard ;
- la transcription automatique des messages ;
- toute modération des messages d'invités : ils vont au client tels quels ;
- l'enregistrement des messages d'invités depuis le web. Ils viennent du
  combiné, qui reste le seul point de captation.

## Modèle de données

### `parties`

| Colonne | Type | Rôle |
| --- | --- | --- |
| `name` | string, **devient nullable** | Le sous-domaine, réservé aux événements « Galerie en ligne » |
| `gallery_uuid` | uuid, unique | Adresse de la future surface invités `/pictures/{uuid}` |
| `client_uuid` | uuid, unique | Adresse de l'espace client `/audiobook/{uuid}` |
| `has_gallery` | bool, défaut `true` | Module galerie photo |
| `has_audio_guestbook` | bool, défaut `false` | Module livre d'or audio |
| `client_email` | string, nullable | Destinataire des e-mails client |
| `event_at` | datetime, nullable | Date de l'événement, venue de la source |
| `greeting_reminder_sent_at` | datetime, nullable | Idempotence de la relance J-7 |
| `expiry_reminder_sent_at` | datetime, nullable | Idempotence de la relance d'expiration |

`expire_at` existe déjà et masque les photos une fois passée ; il vaut désormais
aussi pour l'audio. Un événement a une seule fin de vie. La création depuis la
source le pose à `event_at + 90 jours`.

**Les deux uuid sont distincts et sans lien dérivable.** `gallery_uuid` circule
en QR code et dans les groupes de famille : il est public de fait.
`client_uuid` ouvre l'espace privé du couple — son annonce, l'intégralité des
messages de ses invités, le renommage, le téléchargement groupé. Un uuid unique
laisserait n'importe quel invité passer de l'un à l'autre en changeant le
préfixe de l'URL.

`audiobook` et `pictures` deviennent des noms de party réservés : `/{party}` est
un préfixe attrape-tout, une party portant l'un de ces noms masquerait la route
littérale.

### `audio_greetings`

Une ligne par party, relation `hasOne`. Le cycle de vie porte huit champs qui
n'ont rien à faire sur `parties`, dont sept sont nuls pour toute party sans
livre d'or.

| Colonne | Type | Rôle |
| --- | --- | --- |
| `party_id` | FK, unique | |
| `original_path` | string | Le fichier tel qu'enregistré par le navigateur |
| `mp3_path` | string, nullable | Le MP3 produit par la conversion |
| `duration_seconds` | int, nullable | Lu par ffprobe à la conversion |
| `client_note` | string(120), nullable | La « note pour le studio » de la maquette |
| `status` | enum | `draft`, `pending`, `validated`, `rejected` |
| `rejection_reason` | text, nullable | Obligatoire au refus, affiché au client |
| `submitted_at` | datetime, nullable | |
| `validated_at` | datetime, nullable | |

### `audio_messages`

| Colonne | Type | Rôle |
| --- | --- | --- |
| `party_id` | FK | |
| `path` | string | Le MP3 tel que déposé, aucune transformation |
| `title` | string, nullable | Renommage par le client, jamais obligatoire |
| `duration_seconds` | int | Lu par ffprobe au dépôt |
| `recorded_at` | datetime, nullable | Date de modification du fichier déposé |
| `position` | int | Ordre de passage |

Les fichiers vivent sur le disque `s3` existant, sous `parties/{id}/audio/`.
Ils sont supprimés à la suppression de la party, comme la bannière.

## Le cycle de l'annonce

```
                     client enregistre
        (aucune)  ──────────────────────▶  draft
                                             │
                                  client soumet (+ note)
                                             ▼
                                          pending ───▶ mail studio
                                          ╱      ╲
                            studio valide╱        ╲studio refuse (+ motif)
                                        ▼          ▼
                                  validated      rejected ───▶ mail client
                                        │          │
                                        └────┬─────┘
                                             │ client réenregistre
                                             ▼
                                           draft
```

Le client peut réenregistrer depuis n'importe quel état, y compris `validated` —
la maquette propose « Modifier le message ». Cela repasse l'annonce en `draft`
et le compteur d'étapes revient au début.

La validation ne demande pas de confirmation : elle est réversible, le studio
pouvant repasser une annonce en `rejected` après coup.

## Adressage et accès

Les deux adressages coexistent :

```
camille-antoine.boothparty.fr/          galerie publique, événements « Galerie en ligne »
boothparty.fr/audiobook/{client_uuid}   espace client du livre d'or
boothparty.fr/pictures/{gallery_uuid}   surface invités sans sous-domaine (plus tard)
```

L'uuid **est** l'accès : pas de token, pas de session. Cent vingt-deux bits
d'entropie ne s'énumèrent pas.

### Rate limiting

La clé des limiteurs existants (`ip:party`) serait contre-productive sur une
route d'entrée par uuid : elle donnerait à un attaquant un compteur neuf à
chaque uuid essayé, donc aucune protection contre l'énumération.

| Route | Clé | Ceiling |
| --- | --- | --- |
| `GET /audiobook/{uuid}` | `ip` seule | 30 / min |
| Endpoints internes (dépôt, ZIP, renommage) | `ip:uuid` | comme leurs voisins |

Une party dont `has_audio_guestbook` est faux renvoie 404 sur `/audiobook`, pas
403 : l'existence même de l'espace ne se confirme pas.

## Les routes

```
GET    /audiobook/{uuid}                       page client
POST   /audiobook/{uuid}/annonce               dépôt du fichier enregistré → draft
POST   /audiobook/{uuid}/annonce/soumettre     draft → pending
PATCH  /audiobook/{uuid}/messages/{message}    renommage
POST   /audiobook/{uuid}/telecharger           ZIP de tous les messages

GET    /admin/livre-or                         file d'attente globale
POST   /admin/parties/{party}/livre-or/lien    envoi ou renvoi du lien client
PATCH  /admin/parties/{party}/modules          activation d'un module
GET    /{party}/dashboard/livre-or             la page par party
POST   /{party}/dashboard/livre-or/messages    dépôt des messages
DELETE /{party}/dashboard/livre-or/messages/{message}
POST   /{party}/dashboard/livre-or/annonce/valider
POST   /{party}/dashboard/livre-or/annonce/refuser
```

Les fichiers audio sont servis par URL signée S3 (`SignedStorageUrl`), comme les
photos, sans contrôleur de flux.

## La page client

Une seule page, pas deux phases. La maquette bascule entre « enregistrement » et
« récupération » par un sélecteur d'aperçu ; en production les deux coexistent :
l'annonce en haut, repliée en bandeau une fois validée, et la section des
messages en dessous, qui n'apparaît que lorsque l'admin a déposé quelque chose.
Le client n'a jamais à choisir un mode.

L'enregistrement se fait par `MediaRecorder`, quatre-vingt-dix secondes au plus,
avec réécoute et réenregistrement libres avant la soumission. Le fichier part
tel que le navigateur le produit — WebM/Opus, ou MP4/AAC sur iPhone.

L'état `rejected` manque à la maquette et s'ajoute dans le même langage visuel :
bandeau « À refaire », le motif du studio en toutes lettres, un bouton
« Réenregistrer ».

Le renommage des messages est une édition en place, optionnelle, jamais
bloquante : c'est le client qui reconnaît les voix, le studio n'a pas cette
information et le travail part chez celui qui l'a.

Les composants publics vivent dans `resources/js/components/party/` et
n'utilisent que des utilitaires Tailwind et les variables CSS du projet. Un
composant public important `@/components/ui/*` perdrait silencieusement ses
styles, `app.css` étant compilé séparément de `admin.css`.

## L'interface admin

### `/admin/livre-or`, la file d'attente

Trois listes, ordonnées par urgence :

| Liste | Critère | Actions |
| --- | --- | --- |
| À valider | annonce `pending` | écouter, valider, refuser |
| À déposer | `event_at` passé, aucun message | déposer |
| Sans annonce | `event_at` à venir, annonce absente ou non validée | renvoyer le lien |

Le badge de la sidebar compte « à valider » et « à déposer », les deux qui
demandent une action du studio. « Sans annonce » est informatif : c'est le
client qui est en retard, et la relance J-7 part toute seule.

Le refus ouvre un dialogue avec un motif obligatoire, qui part dans l'e-mail au
client.

### La page par party

`/{party}/dashboard/livre-or` porte le travail sur un événement précis :
l'annonce avec son lecteur et ses actions, le dépôt des messages en
glisser-déposer multi-fichiers sur le modèle de `AdminGalleryController::store`,
et la liste des messages déposés avec suppression unitaire. C'est là
qu'atterrissent les liens des e-mails d'alerte.

### La sidebar

Un module inactif n'est pas masqué : son entrée reste visible, en pointillés, et
le clic ouvre une modale de confirmation qui l'active sur la party. Le studio
découvre ainsi le produit qu'il n'a pas vendu à ce client, et l'active sans
détour par `/admin/parties`.

```
Party
  Dashboard
  Pictures
  ┊ Livre d'or ┊   ← pointillés, cliquable
```

L'activation passe par `PATCH /admin/parties/{party}/modules`, le seul endroit
qui bascule un module. La section Administration gagne son entrée `Livre d'or`
avec son badge.

### `/admin/parties`

Trois retouches, pas une page de plus :

- une colonne `Modules` avec ses pastilles ;
- le dialogue de détails gagne `client_email`, les deux cases de modules, un
  bouton « Envoyer le lien livre d'or » et un bouton de révocation qui
  régénère `client_uuid` ;
- le dialogue de création depuis la source affiche les modules et l'e-mail lus
  de la source, modifiables avant création.

## La source d'événements

`NotionPartySource` filtre aujourd'hui sur l'option multi-select « Galerie en
ligne ». Le filtre s'élargit aux événements portant cette option **ou** l'option
du livre d'or audio ; les options cochées déterminent les modules, et une
propriété Notion fournit l'e-mail du client. `PartySourceEvent` gagne
`clientEmail`, `hasGallery` et `hasAudioGuestbook`, et expose déjà `startsAt`
qui alimente désormais `event_at`.

Les noms exacts des propriétés Notion vont en configuration, comme les noms
existants.

## Les e-mails

Tous en `Mailable` mis en file. Un seul canal : la couche `Notification`
n'apporterait rien.

| Mailable | Destinataire | Déclencheur |
| --- | --- | --- |
| `AudiobookReady` | client | bouton admin « Envoyer le lien » |
| `GreetingSubmitted` | studio | soumission par le client |
| `GreetingValidated` | client | validation |
| `GreetingRejected` | client | refus, porte le motif |
| `MessagesAvailable` | client | fin du dépôt, avec nombre et durée totale |
| `GreetingReminder` | client | J-7 de `event_at`, annonce non validée |
| `ExpiryReminder` | client | J-7 de `expire_at`, si des messages existent |

`AudiobookReady` n'est jamais automatique : l'admin décide quand le client est
prêt à le recevoir.

Les deux derniers partent d'une commande `audiobook:remind` planifiée
quotidiennement. Les colonnes `greeting_reminder_sent_at` et
`expiry_reminder_sent_at` en garantissent l'idempotence : une simple fenêtre de
sélection suffirait un jour normal, mais un jour où le planificateur ne tourne
pas, la relance serait perdue en silence.

## Le traitement des fichiers

L'annonce arrive du navigateur en WebM ou en MP4 et doit finir en MP3, format
attendu par le combiné. Un job `ConvertGreetingToMp3` s'en charge, calqué sur
`CreatePictureThumbnail`, et lit la durée au passage.

Les messages des invités arrivent déjà en MP3 du combiné : aucune conversion,
seulement la lecture de leur durée et de leur date de modification. Le ZIP
« tout télécharger » les empaquette tels quels, nommés par ordre de passage,
avec `ZipStream` déjà utilisé pour les photos.

## Ce qu'il faut ajouter à l'infrastructure

1. **Un transporteur mail réel.** `MAIL_MAILER=log` aujourd'hui, aucun
   `app/Mail` ni `app/Notifications`. SMTP et `MAIL_FROM_ADDRESS` dans les
   secrets Kamal, plus l'adresse d'équipe destinataire de `GreetingSubmitted`.
2. **ffmpeg dans l'image Docker**, pour la conversion et pour ffprobe.
3. **Un rôle `scheduler` dans Kamal**, `php artisan schedule:work`. Le rôle
   `worker` existe déjà pour la file.

## Tests

- **Accès** : uuid inconnu → 404 ; `gallery_uuid` sur `/audiobook` → 404 ;
  module inactif → 404 ; le limiteur d'entrée bloque l'énumération.
- **Cycle de l'annonce** : dépôt → `draft` ; soumission → `pending` et mail
  studio ; validation → `validated` et mail client ; refus → `rejected`, motif
  présent dans le mail ; réenregistrement depuis `validated` et depuis
  `rejected`.
- **Messages** : le dépôt crée les lignes avec ordre et durée ; renommage
  client ; ZIP contenant les bons fichiers dans le bon ordre ; suppression
  unitaire.
- **Modules** : la sidebar affiche l'entrée d'un module inactif en pointillés ;
  la modale l'active ; la file d'attente classe chaque party dans la bonne
  liste.
- **Source** : un événement portant l'option audio crée une party avec le bon
  module, l'e-mail et `event_at`.
- **Relances** : `audiobook:remind` envoie une fois et une seule, ne renvoie pas
  le lendemain, et rattrape un jour manqué.
- **Conversion** : job mis en file à la soumission (`Queue::fake()`), plus un
  test d'intégration sur la conversion réelle.

`Mail::fake()` partout, sauf un test de rendu par mailable pour attraper les
variables manquantes dans les gabarits.

## Décisions écartées

| Option | Pourquoi non |
| --- | --- |
| Entité `AudioGuestbook` séparée | Un client ayant les deux produits apparaîtrait dans deux listes, avec deux liens et deux dates d'expiration pour un seul événement. |
| Colonne `type` sur `parties` | Moitié des colonnes nulles selon le type, validations conditionnelles partout. |
| Nommage des messages par le studio | Environ une minute par message ; le studio ne reconnaît pas les voix. Le client, si. |
| Transcription automatique | Nouvelle dépendance, coût à la minute, et des voix d'invités envoyées à un tiers. |
| Conversion des messages invités | Ils arrivent déjà en MP3. |
| Un uuid unique par party | Le lien photos, public de fait, donnerait l'espace privé du client. |
| Lien signé Laravel | Une signature ne se révoque pas à l'unité. |
| Session hôte (`ResolvePartyHost`) | Le client veut un lien qu'il met en favori et rouvre des mois plus tard. |
