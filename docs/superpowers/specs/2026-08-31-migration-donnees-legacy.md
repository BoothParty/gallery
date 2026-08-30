# Migration des données du projet legacy

Date : 2026-08-31

## Contexte

Ce projet remplace `BoothParty/BoothParty`, l'application Laravel qui sert
aujourd'hui les galeries en production. Le nouveau schéma est en place, mais la
base est vide : tant que les galeries existantes n'y sont pas, la bascule est
impossible.

Les deux schémas divergent sur trois points qui font tout le travail de cette
migration :

- l'ancien projet rattache ses parties à des utilisateurs par une table pivot
  `user_parties`, quand le nouveau porte un `user_id` unique et nullable ;
- l'ancien projet stocke ses photos sur le **disque local** du serveur, sous
  `public/pictures/{party_name}/{picture_id}/{md5}.jpg`, quand le nouveau les
  pousse sur **S3/Minio** ;
- l'ancien projet éclate chaque photo en trois lignes de `picture_sizes`
  (`original`, `thumbnail` 250 px, `cover` 500×500), quand le nouveau tient
  `path`, `thumbnail_path`, `width` et `height` sur la ligne `pictures`.

La production compte **185 481 fichiers JPEG** pour **47 Go**, soit de l'ordre
de **62 000 photos** — environ 650 Ko par original — dont une part
d'orphelins : des fichiers sur le disque qui ne correspondent à aucune ligne en
base.

## Objectif

Importer, en une passe rejouable, l'intégralité des parties et des photos de
l'ancienne base vers le nouveau projet, fichiers compris.

## Périmètre

Dans le périmètre :

- les parties, toutes, y compris celles dont la date de masquage est passée ;
- leurs photos : la ligne en base, le fichier original poussé sur S3, la
  miniature régénérée ;
- une connexion de base de données `legacy` dédiée à la lecture de la source ;
- une commande Artisan unique, avec un mode simulation.

Hors périmètre :

- **les utilisateurs.** Le nouveau projet authentifie par OAuth et n'a plus de
  mot de passe ; les comptes legacy n'ont rien à y faire. Les parties importées
  gardent `user_id` à `null`, ce que la colonne autorise déjà.
- **`frames`, `reservations`, `configurations`.** Ces tables n'ont aucun
  équivalent dans le nouveau schéma et l'import ne va pas en inventer.
- **`location`, `date` et `is_from_time`** de l'ancienne table `parties`. Les
  métadonnées d'événement ont été délibérément repoussées
  (`docs/superpowers/specs/2026-08-27-metadonnees-evenement.md`) ; ces trois
  colonnes sont donc **perdues**, décision prise en connaissance de cause.
- toute synchronisation continue entre les deux bases. C'est un import unique.

## Dépendance de branche

La branche part de `feature/gallery-host-editor`, et non de `main`. `main` porte
bien `CreatePictureThumbnail`, `source_key`/`source_event_id` et les colonnes
`width`/`height`, mais **pas** `display_name` ni `description`, sur lesquelles
repose le mapping. Cet import ne peut pas être fusionné avant l'éditeur d'hôte.

## Accès à la source

L'import travaille hors ligne sur deux artefacts copiés depuis la production :

1. un dump SQL restauré dans une base MySQL locale ;
2. une copie du dossier `public/` de l'ancien projet, récupérée par `rsync`.

La lecture passe par une connexion `legacy` déclarée dans
`config/database.php`, alimentée par des variables `LEGACY_DB_*` et documentée
dans `.env.example`. Aucun modèle Eloquent n'est créé pour la source : trois
appels `DB::connection('legacy')->table(...)` suffisent, et le projet n'hérite
pas de modèles qui n'auront plus de raison d'exister après la bascule.

`picture_sizes.path` est déjà relatif à la racine `public/` de l'ancien projet.
Le chemin d'un fichier sur le disque est donc exactement
`{--files}/{picture_sizes.path}`, sans reconstruction.

## La commande

`app/Console/Commands/Party/ImportLegacyData.php`, signature
`party:import-legacy`, dans le dossier où vit déjà `CreateParty`.

| Option | Rôle |
| --- | --- |
| `--files=` | Racine du `public/` copié depuis la production. Obligatoire. |
| `--dry-run` | Parcourt tout et produit le rapport sans écrire ni en base ni sur S3. |
| `--party=` | Restreint à une party legacy, par son `name`. |

L'ordre d'exécution prévu est `--dry-run` d'abord, pour obtenir le décompte réel
et le nombre d'orphelins ; puis `--party=` sur une galerie, pour valider le
rendu à l'œil ; puis l'import complet.

## Mapping

### Parties

| Source (legacy) | Cible | Note |
| --- | --- | --- |
| `name` | `name` | Sous-domaine et route key, repris tel quel |
| `title` | `display_name` | |
| `description` | `description` | |
| `hide_pictures_date` | `expire_at` | Même sémantique : passé ⇒ photos masquées |
| `id` | `source_event_id` | Avec `source_key = 'legacy'` |
| — | `user_id` | `null` : les comptes ne sont pas migrés |
| — | `host_token` | Généré par le hook `creating` du modèle |
| `created_at` / `updated_at` | idem | Préservés |

`location`, `date` et `is_from_time` sont abandonnés (voir Périmètre).

Les `created_at`/`updated_at` ne sont pas dans `$fillable` : ils sont posés par
`forceFill` avant le `save`, faute de quoi Eloquent les écraserait par la date
de l'import et toutes les galeries paraîtraient créées le même jour.

### Photos

Une ligne `pictures` par photo legacy, alimentée depuis la ligne
`picture_sizes` de taille `original` — les tailles `thumbnail` et `cover` sont
ignorées, la miniature étant régénérée.

| Source | Cible |
| --- | --- |
| fichier `{--files}/{path}` | objet S3 `pictures/{party_id}/{md5}.jpg` |
| `picture_sizes.width` | `width` |
| `picture_sizes.height` | `height` |
| `pictures.created_at` / `updated_at` | idem |

Le `md5` est le nom de fichier d'origine : l'ancien projet nommait déjà ses
fichiers par le hash de leur contenu. On le réutilise tel quel, ce qui donne un
chemin S3 **déterministe** — la propriété sur laquelle repose toute la reprise.

`width` et `height` viennent de la base, pas du fichier : aucune image n'est
décodée pendant l'import.

## Idempotence

L'import doit être rejouable, non parce que la base cible contiendrait déjà
quelque chose — elle est vide — mais parce qu'un transfert de 62 000 photos
dure des heures et **sera** interrompu au moins une fois. Une reprise qui
repartirait de zéro en dupliquant tout n'est pas acceptable.

La reprise ne demande pourtant aucun état persisté, parce que les deux clés de
déduplication sont déterministes :

- une party se crée par `firstOrCreate` sur le couple
  `['source_key' => 'legacy', 'source_event_id' => (string) $legacyId]` ;
- une photo se crée par `firstOrCreate` sur son `path`, dérivé du md5 legacy ;
- `putFileAs` sur un objet S3 déjà présent l'écrase à l'identique.

Relancer la commande reprend donc là où elle s'était arrêtée, au prix de
quelques `HEAD` S3 sur ce qui est déjà passé. Il n'y a pas de `--resume` à
gérer, et pas de table de suivi à maintenir.

Le couple `source_*` sert accessoirement de bouton d'annulation : une seule
requête sur `source_key = 'legacy'` identifie tout ce que l'import a produit.

## Flux

Pour chaque party legacy, prise en `orderBy('id')` :

1. `firstOrCreate` de la party selon le mapping ci-dessus ;
2. ses photos sont parcourues en `chunkById(500)` sur une jointure
   `pictures` × `picture_sizes` filtrée sur `size = 'original'` — un seul
   passage, sans N+1, et à empreinte mémoire plate quelle que soit la taille de
   la galerie ;
3. si le fichier est absent du disque, la photo est comptée comme manquante et
   passée ;
4. sinon `putFileAs("pictures/{$party->id}", new File($chemin), "{$md5}.jpg")`,
   puis `firstOrCreate` de la ligne, ses timestamps d'origine posés par
   `forceFill` comme pour les parties ;
5. si la ligne vient d'être créée, `CreatePictureThumbnail::dispatch($picture)`.

### Les miniatures restent hors du chemin critique

62 000 décodages et ré-encodages représentent plusieurs heures de CPU. La
commande ne les fait pas : elle dispatche `CreatePictureThumbnail`, que des
workers absorbent en parallèle. Le transfert vers S3 et la génération des
vignettes progressent alors ensemble, et une vignette ratée se relance seule
sans rejouer l'import.

Le job produit une miniature de 480 px de plus grande arête, en qualité 80,
sous `pictures/{party_id}/{md5}_thumb.jpg` — chemin déterministe lui aussi,
donc rejouable sans dégât.

**Compromis assumé :** le job relit l'original depuis S3 (`$disk->get`), alors
que l'import vient tout juste de le lire sur le disque local. Chaque photo
traverse donc le réseau deux fois. Sur un Minio auto-hébergé sur le même
réseau, cela représente deux fois ~40 Go, du temps et non de l'argent, et cela
évite de dupliquer la logique de vignette dans la commande. Si le débit devient
le facteur limitant, le levier est d'ajouter au job un chemin de lecture local
optionnel — pas de réécrire l'import.

## Erreurs

Une photo qui échoue ne fait jamais tomber l'import : chaque itération est
enveloppée dans un `try`/`catch` qui journalise l'ancien identifiant et
poursuit. Sur 62 000 fichiers d'origine hétérogène, un JPEG tronqué ou un
chemin exotique est une certitude, pas une hypothèse.

La commande tient quatre compteurs — parties créées, photos importées, fichiers
manquants, erreurs — affichés en tableau à la fin, et une barre de progression
alimentée par le `count()` initial.

Les fichiers orphelins du disque ne demandent aucun traitement : l'import est
piloté par les lignes de `picture_sizes`, jamais par un parcours du système de
fichiers. Un fichier sans ligne en base n'est simplement jamais regardé. C'est
le cas inverse — une ligne dont le fichier a disparu — qui alimente le compteur
« fichiers manquants ».

## Tests

`tests/Feature/Console/ImportLegacyDataTest.php` monte une connexion `legacy`
SQLite en mémoire portant le schéma d'origine (`parties`, `pictures`,
`picture_sizes`), un dossier temporaire de fichiers JPEG factices,
`Storage::fake('s3')` et `Queue::fake()`.

Cas couverts :

- le mapping complet d'une party, `expire_at` et timestamps préservés compris ;
- l'upload au chemin S3 attendu, avec `width`/`height` repris de la source ;
- un `CreatePictureThumbnail` dispatché par photo créée ;
- une ligne dont le fichier manque : comptée, non fatale, l'import continue ;
- une party sans photo ;
- **une seconde exécution qui ne crée ni party ni photo en double**, et ne
  redispatche aucun job ;
- `--dry-run` : aucune écriture en base, aucun objet sur S3, rapport produit.

## Déroulé opérationnel

1. Transfert dimensionné : 47 Go, ~62 000 photos (mesuré le 2026-08-31).
2. `rsync` incrémental du dossier `public/` et dump de la base.
3. Restauration du dump dans la base locale, `LEGACY_DB_*` renseignées.
4. `party:import-legacy --files=… --dry-run` : décompte et orphelins.
5. `party:import-legacy --files=… --party=<une-galerie>` : validation visuelle.
6. Import complet sous `tmux`, workers de queue lancés en parallèle.
7. Contrôle : nombre de parties, nombre de photos, part de `thumbnail_path`
   encore nulles une fois la queue vidée.
