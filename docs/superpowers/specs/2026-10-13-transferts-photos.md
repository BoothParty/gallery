# Transferts de photos

Date : 2026-10-13

## Contexte

Quand un client n'a pas pris l'option galerie en ligne, les photos de la borne
lui sont aujourd'hui livrées via fromsmash : on dépose le dossier, on lui donne
un nom, on envoie, puis on colle le lien dans le mail de retour. Ça fonctionne,
mais le client reçoit un lien de téléchargement brut plutôt que notre galerie,
et les photos vivent hors de notre stockage.

## Objectif

Reproduire ce geste depuis l'administration : déposer un dossier, saisir un
nom, envoyer, copier un lien. Le client ouvre ce lien sur la galerie publique
existante, servie sous `https://<domaine-de-l-app>/transfers/<uuid>`.

## Périmètre

Dans le périmètre :

- une colonne `kind` sur `parties` distinguant les événements des transferts ;
- une colonne `original_name` sur `pictures`, base de la reprise sans doublon ;
- la route publique `/transfers/{party}` ;
- un écran d'administration « Transferts » : tableau, création, suppression ;
- un dépôt de dossier robuste qui envoie les photos une par une ;
- l'adaptation de l'endpoint d'upload admin existant.

Hors périmètre :

- la purge des fichiers S3 après expiration, ou après suppression d'une party :
  la cascade `pictures.party_id` supprime les lignes en base sans déclencher
  `Picture::deleted`, les fichiers restent donc sur S3. C'est déjà le cas pour
  les parties ;
- un téléchargement complet sans plafond : le ZIP garde la limite
  `party.download.max_pictures` (300), comme pour les parties ;
- le lien d'édition hôte pour un transfert : le client a la galerie en lecture
  seule. Le `host_token` est généré comme pour toute party mais jamais exposé ;
- le blocage de la galerie d'un transfert sur `GET /{uuid}` : elle y reste
  accessible, l'uuid restant le seul secret ;
- les audios du livre d'or.

## Modèle de données

Un transfert est une `Party`, sans nouvelle table. Il réutilise `pictures`, les
miniatures, la page `party/gallery`, le ZIP et `expire_at`.

### `parties.kind`

Migration : `kind` string, défaut `event`, valeurs portées par un enum PHP
`App\Enums\PartyKind` (`Event`, `Transfer`), casté sur le modèle. Les parties
existantes deviennent des `event` sans autre changement.

Un transfert est créé avec :

| Colonne        | Valeur                                              |
|----------------|-----------------------------------------------------|
| `kind`         | `transfer`                                          |
| `name`         | `Str::uuid()`, clé de route et secret du lien       |
| `display_name` | le nom saisi, titre de la galerie                   |
| `expire_at`    | optionnel, vide par défaut                          |
| `user_id`      | `null`                                              |

### `Party::publicUrl()`

Pour un transfert, renvoie `url('/transfers/'.$this->name)`. Pour un événement,
le comportement ne change pas. `publicHost()`, `posterUrl()` et
`hostEditUrl()` ne sont pas appelés pour un transfert.

### `pictures.original_name`

Migration : `original_name` string nullable, index unique
(`party_id`, `original_name`). Les valeurs nulles ne se heurtent pas : les
photos envoyées par la borne gardent `null` et rien ne change pour elles.

La valeur est le chemin du fichier relatif au dossier déposé, racine exclue
(`IMG_0042.jpg`, `raw/IMG_0042.jpg`). Ce chemin distingue deux fichiers de même
nom dans des sous-dossiers différents, et ne dépend pas du nom du dossier
racine : redéposer le même dossier renommé reconnaît toujours ses fichiers.

## Routes

```php
Route::get('transfers/{party}', [PartyController::class, 'show'])
    ->middleware(ResolvePartyHost::class)
    ->name('transfers.show');
```

Déclarée **avant** le groupe `Route::prefix('{party}')`, faute de quoi
`/transfers/dashboard` serait capté par le dashboard d'une party nommée
`transfers`. `PartyController::show` répond 404 quand la route est
`transfers.show` et que la party n'est pas un transfert.

Les requêtes internes de la galerie (`/{name}/download`,
`/{name}/pictures/{id}`, visibilité) sont construites à partir de `party.name`
et continuent de passer par les routes `/{party}` existantes.

Administration, dans le groupe `admin` :

```php
Route::resource('transfers', AdminTransferController::class)->only(['index', 'store', 'destroy']);
```

L'écran Parties (`Admin\PartyController::index`) filtre sur `kind = event`.

## Écran d'administration

Entrée « Transferts » dans la sidebar, entre Parties et Utilisateurs.

Tableau, une ligne par transfert, du plus récent au plus ancien : nom, nombre
de photos, date de création, expiration. Actions par ligne :

- **Copier le lien** : `publicUrl()` dans le presse-papiers ;
- **Ouvrir la galerie** ;
- **Compléter** : rouvre le dépôt de dossier sur ce transfert (voir Reprise) ;
- **Supprimer**, avec confirmation.

Bouton « Nouveau transfert » : une boîte de dialogue portant le nom (requis),
l'expiration (optionnelle, `datetime-local` comme l'écran Parties) et la zone de
dépôt. « Envoyer » crée le transfert (`POST admin/transfers`, qui renvoie son
`name`), puis lance la file d'envoi. En fin d'envoi, la boîte affiche le lien et
un bouton « Copier ».

## Dépôt de dossier

Composant front autonome, réutilisé par la création et par « Compléter ».

### Sélection

Deux voies, natives, sans dépendance :

- **glisser-déposer** : parcours récursif via
  `DataTransferItem.webkitGetAsEntry()`. `readEntries()` rend le contenu d'un
  dossier par lots (100 au plus sous Chrome) : on le rappelle jusqu'à obtenir un
  lot vide, sinon un dossier de 480 photos n'en remonterait que 100 ;
- **clic** : `<input type="file" webkitdirectory multiple>`, le chemin relatif
  venant de `File.webkitRelativePath`.

Dans les deux cas, le premier segment du chemin (le dossier racine) est retiré
pour former l'`original_name`.

### Analyse

Rien ne part avant validation d'un récapitulatif.

- Sont retenus les fichiers JPEG et PNG (type MIME, à défaut extension).
- Sont ignorés sans bruit `.DS_Store`, `._*`, `Thumbs.db` et tout fichier caché.
- Sont écartés et listés les fichiers de plus de 10 Mo, la limite de
  `StoreAdminPicturesRequest`.
- Les photos retenues sont regroupées par sous-dossier, chacun avec son compte
  et une case à cocher (par exemple `/` : 240, `raw/` : 240), pour exclure un
  dossier de doublons bruts. L'arborescence exacte produite par pibooth reste à
  vérifier sur une borne.

L'envoi se fait par `original_name` croissant. Les noms pibooth étant
horodatés et la galerie triant du plus récent au plus ancien, le transfert
s'affiche comme une galerie en direct : la dernière photo de la soirée en tête.

### File d'envoi

- Une photo par requête, 3 requêtes simultanées au plus (constante
  `CONCURRENCY`).
- Erreur réseau, 5xx ou 429 : jusqu'à 3 nouvelles tentatives, après 1 s, 3 s
  puis 9 s ; le fichier passe ensuite en échec.
- 422 : échec définitif du fichier, sans nouvelle tentative.
- 401 ou 419 : la file entière s'arrête, avec le message « Session expirée,
  reconnecte-toi puis redépose le dossier ». Les requêtes suivantes échoueraient
  toutes de la même façon.
- Progression affichée : `312 / 480`, volume envoyé, déjà présents, échecs,
  écartés.
- « Annuler » interrompt les requêtes en cours (`AbortController`) ; ce qui est
  déjà envoyé reste.
- « Réessayer les échecs » relance les seuls fichiers en échec.
- Pendant l'envoi : `beforeunload` demande confirmation avant de quitter, et un
  verrou `navigator.wakeLock.request('screen')` empêche la mise en veille quand
  le navigateur le permet. Le verrou est relâché en fin d'envoi ou d'annulation.

### Reprise

Reprendre un transfert interrompu (onglet fermé, coupure, session expirée)
revient à redéposer le même dossier via « Compléter » : le serveur reconnaît
les fichiers déjà présents par leur `original_name` et ne stocke que les
manquants. Une relance après un timeout dont la requête avait en réalité
abouti ne crée pas de doublon pour la même raison.

## Endpoint d'upload

L'envoi réutilise `POST /{party}/dashboard/pictures`
(`Admin\GalleryController::store`), sans nouvel endpoint. Il évolue ainsi :

- `StoreAdminPicturesRequest` accepte un tableau `paths` optionnel, aligné sur
  `photos`, chaque entrée une chaîne de 255 caractères au plus. À défaut,
  `original_name` reprend `getClientOriginalName()`. Le nom de fichier passé à
  `FormData.append` ne suffit pas : Symfony n'en garde que le nom de base ;
- pour chaque photo, si la party a déjà une photo de même `original_name`, elle
  est comptée « déjà présente » et rien n'est stocké. La vérification a lieu
  avant l'écriture S3 ;
- deux envois simultanés du même fichier peuvent passer cette vérification :
  l'index unique tranche, et le perdant supprime le fichier qu'il vient
  d'écrire sur S3 puis compte la photo « déjà présente » ;
- quand la requête attend du JSON, la réponse est `200`
  `{"created": n, "skipped": m}` au lieu de la redirection, sinon le
  formulaire du dashboard garde son comportement actuel ;
- l'activité `PicturesUploaded` n'est enregistrée que si `created > 0`.

L'upload depuis le dashboard d'une party profite de la déduplication.

À vérifier en production : la taille de corps de requête acceptée par le proxy
(`client_max_body_size` sous nginx). Une photo par requête rend ce point peu
risqué, mais une valeur par défaut d'1 Mo bloquerait tout envoi.

## Tests

Feature (Pest) :

- `transfers.show` rend la galerie d'un transfert et répond 404 pour un
  événement ;
- `admin.transfers.store` crée une party `transfer` au `name` uuid et au
  `display_name` saisi ; `index` ne liste que les transferts et
  `admin.parties.index` que les événements ;
- `publicUrl()` d'un transfert pointe sur `/transfers/<uuid>` ;
- l'upload JSON renvoie `created` / `skipped`, ne stocke pas un fichier déjà
  présent, et n'écrit pas sur S3 dans ce cas ;
- l'upload avec `paths` enregistre le chemin relatif dans `original_name` ;
- l'upload de formulaire classique redirige toujours vers le dashboard.

Front : le parcours récursif avec lecture par lots, le filtrage et la file
d'envoi (relances, arrêt sur 419) sont vérifiés à la main dans Chrome et
Firefox, sur un vrai dossier de borne.
