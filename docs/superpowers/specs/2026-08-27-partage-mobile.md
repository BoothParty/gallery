# Partage mobile de la sélection

Date : 2026-08-27

## Contexte

La galerie publique ne propose qu'un ZIP pour récupérer une sélection
(`docs/superpowers/specs/2026-08-27-galerie-publique-refonte.md`). Sur
téléphone, un ZIP est un cul-de-sac : iOS le dépose dans Fichiers, Android dans
Téléchargements, et les photos n'atteignent la galerie de l'appareil qu'au prix
d'une extraction manuelle. Or l'essentiel des invités d'un photobooth arrive
depuis un téléphone.

La Web Share API de niveau 2 accepte des fichiers et ouvre la feuille de
partage native, d'où l'invité enregistre les images dans sa pellicule ou les
envoie dans une conversation, sans passer par un gestionnaire de fichiers.

## Objectif

Offrir le partage natif comme chemin principal sur les appareils qui le
supportent, en gardant le ZIP comme repli partout ailleurs.

## Périmètre

Dans le périmètre :

- une route publique qui sert une photo depuis l'origine de l'application ;
- un hook de partage détenant le téléchargement, l'appel à `navigator.share`
  et le traitement des erreurs ;
- la bascule interne du bouton de téléchargement entre partage natif et ZIP.

Hors périmètre :

- le partage d'un lien de sélection ;
- des dérivées web des photos, entre la miniature de 480 px et l'original ;
- un plafond propre au partage : celui du ZIP sert aux deux chemins ;
- toute modification du chemin ZIP, qui reste le repli.

## Décision d'affichage

La barre de sélection garde un seul bouton, dont le libellé reste
« Télécharger » en toutes circonstances. Le partage natif est un détail
d'implémentation : l'invité demande ses photos, le navigateur les lui donne du
mieux qu'il peut. Le libellé ne varie jamais, y compris après une activation
expirée : le bouton redevient simplement « Télécharger ».

Deux chemins derrière ce libellé :

- si le navigateur répond vrai à `navigator.canShare({ files })` appelé avec un
  `File` image témoin, le bouton télécharge les photos sélectionnées puis ouvre
  la feuille de partage native ;
- sinon, le bouton reste le `submit` du formulaire ZIP actuel, inchangé.

Aucun plafond propre au partage n'est introduit. Le seul plafond reste
`maxDownloadPictures`, déjà connu du client et déjà signalé par le toast
existant ; il s'applique désormais aux deux chemins.

Le support est détecté à chaque ouverture du mode sélection plutôt qu'une fois
pour toutes au montage de la page. Cela reste correct — le support d'un
navigateur ne change pas en cours de session — et rend le chemin testable au
navigateur, où le stub de `navigator.share` s'installe après le chargement.

## Route de fichier

| Route | Nom | Rôle |
| --- | --- | --- |
| `GET /{party}/pictures/{picture}` | `party.pictures.show` | Sert l'original d'une photo depuis l'origine de l'application |

La Web Share API exige de vrais `File`, donc un `fetch` par photo. Les URLs de
la grille pointent sur S3 ou Minio, une autre origine : les lire en JavaScript
demanderait du CORS sur le bucket, configuration qui vit hors du dépôt,
échappe aux tests et casse silencieusement si elle est perdue. La route proxy
supprime la contrainte et applique au partage les mêmes règles qu'au ZIP.

Le groupe porte `scopeBindings()`, de sorte qu'une photo appartenant à une
autre soirée donne 404 plutôt qu'une fuite. `PictureFileController`, invokable,
renvoie 403 quand `$party->hidePictures()` est vrai, sinon streame
`Storage::disk('s3')->readStream()` avec :

- `Content-Type: image/jpeg` ;
- `Content-Disposition: inline` ;
- `Cache-Control: public, max-age=31536000, immutable`, le même que les objets
  S3 : le chemin est immuable, navigateurs et CDN peuvent le garder
  indéfiniment.

Un nouveau limiteur `party-picture`, déclaré dans `AppServiceProvider` à côté
de `party-download`, autorise 240 requêtes par minute sur une clé IP + party.
Un partage de vingt photos consomme déjà vingt requêtes et les invités d'une
même salle partagent souvent le wifi, donc une clé sur l'IP seule les ferait se
limiter les uns les autres.

La route sert les originaux. Les seules dérivées existantes sont les miniatures
de 480 px, trop petites pour un souvenir.

## Hook de partage

`resources/js/hooks/use-photo-share.ts` détient toute la logique et expose :

```ts
{ supported, status, progress, share(photos), reset() }
```

`status` vaut `idle`, `preparing`, `ready` ou `sharing`. `progress` compte les
fichiers téléchargés sur le total.

`share()` télécharge les photos par la route de fichier, trois requêtes en
parallèle au plus pour ne pas saturer un lien mobile, construit un `File` par
photo — nommé `photo-1.jpg`, `photo-2.jpg`, de type `image/jpeg` — puis appelle
`navigator.share({ files })`. Les fichiers sont relâchés dès que la feuille de
partage s'est fermée, pour que l'onglet ne garde pas la sélection en mémoire
pendant que l'invité continue de parcourir la galerie.

Une grande sélection tient donc en mémoire le temps du partage : trois cents
originaux représentent plus d'un gigaoctet, et un onglet mobile peut se
recharger sans message dans ces conditions. C'est le compromis assumé du
partage natif ; le plafond `maxDownloadPictures` reste la seule borne.

L'appel ne porte ni `title` ni `url` : sur iOS, joindre une URL pousse
plusieurs cibles de partage à envoyer le lien à la place des images.

### Activation et erreurs

`navigator.share()` exige une activation utilisateur transitoire, que Chrome
limite à quelques secondes. Le téléchargement de la sélection peut la consommer.
Le hook tente donc le partage immédiatement après le téléchargement, et traite
trois cas d'échec :

| Cas | Traitement |
| --- | --- |
| `AbortError` | L'invité a fermé la feuille de partage : retour à `idle`, aucun message |
| Autre échec de `share`, fichiers en mémoire | Passage en `ready` ; le bouton redevient « Télécharger » et rejoue `navigator.share` sur le geste suivant, qui porte une activation fraîche, sans rien re-télécharger |
| Échec d'un téléchargement | Toast d'erreur, retour à `idle`, la sélection reste intacte |

Le cas nominal reste donc à un seul geste, et l'activation perdue se rattrape
sans que l'invité ait à re-télécharger quoi que ce soit.

## Barre de sélection

`selection-bar.tsx` conserve son `<form method="post">` : le ZIP reste une
soumission native, dont la spec de la refonte détaille le pourquoi. Le
composant reçoit quatre props : `mode` (`share` ou `download`), `shareStatus`,
`shareProgress` et `onShare`.

En mode partage, le bouton devient un `type="button"` dont le libellé suit
l'état :

| État | Libellé | Bouton |
| --- | --- | --- |
| `idle` | « Télécharger » | actif |
| `preparing` | « Téléchargement… 3/12 », barre de progression | désactivé |
| `ready` | « Télécharger » | actif |
| `sharing` | « Télécharger » | désactivé |

La barre de progression est un filet plein sous le libellé, à la largeur du
rapport entre les fichiers reçus et le total.

En mode ZIP, rien ne change : « Télécharger le ZIP », ou « Télécharger » pour
une photo seule.

`gallery.tsx` calcule le mode, branche le hook sur son toast existant et passe
les props. Aucun autre composant de la galerie n'est touché.

## Configuration

Aucune nouvelle clé de configuration. Le partage réutilise
`party.download.max_pictures`, déjà exposé à la page en prop
`maxDownloadPictures`.

## États limites

| État | Rendu |
| --- | --- |
| Navigateur sans Web Share de niveau 2 | Chemin ZIP, comportement actuel |
| Sélection au-dessus de `maxDownloadPictures` | Toast existant, aucun téléchargement, sur les deux chemins |
| Feuille de partage fermée par l'invité | Barre inchangée, sélection conservée |
| Activation expirée | Bouton « Télécharger » à nouveau actif, sans nouveau téléchargement |
| Photo indisponible sur S3 | Toast d'erreur, aucun partage partiel |
| Galerie expirée | La route de fichier renvoie 403, comme le ZIP |

## Tests

`tests/Feature/PictureFileTest.php` — nouveau :

- la route renvoie 200, les octets de la photo et `Content-Type: image/jpeg` ;
- l'en-tête `Cache-Control` immuable est posé ;
- une photo appartenant à une autre party renvoie 404 ;
- une party expirée renvoie 403 ;
- au-delà de 240 requêtes par minute pour une même party et IP, 429.

`tests/Web/GalleryTest.php` — étendu :

- sans support du partage, le bouton soumet toujours le formulaire ZIP ;
- avec un `navigator.share` simulé qui enregistre son argument sur `window`,
  le bouton lui transmet autant de fichiers que de photos sélectionnées ;
- la barre de progression apparaît pendant le téléchargement ;
- un `AbortError` simulé laisse la barre utilisable et la sélection intacte.

Les tests navigateur de `tests/Web/` sont connus pour flotter sur des délais de
5 s : les relancer avant d'enquêter.

## Séquence de mise en œuvre

1. Route de fichier, contrôleur, limiteur, tests de fonctionnalité.
2. Hook `use-photo-share`.
3. Bascule du bouton dans la barre de sélection et branchement dans
   `gallery.tsx`.
4. Tests navigateur, `pint`, suite complète.
