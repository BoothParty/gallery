# Refonte de la galerie publique

Date : 2026-08-27

## Contexte

La galerie publique `{party}/` a été dessinée dans Claude Design (projet
`96f9683b-4bd3-4cb2-b67c-fe90f366f25c`, fichier `Galerie Photobooth.dc.html`).
La maquette rompt avec l'existant sur trois axes : un thème clair chaud à la
place du thème sombre Partiful, un en-tête d'événement avec photo de couverture,
et deux fonctionnalités absentes du code — le chargement des photos au scroll et
la sélection multiple pour téléchargement groupé.

L'implémentation actuelle (`resources/js/pages/party/gallery.tsx`) pagine par
30 avec deux boutons, empile les photos dans un conteneur flex au ratio natif,
et n'offre le téléchargement qu'unitairement depuis la lightbox PhotoSwipe.

## Objectif

Porter la maquette en Inertia + React + Tailwind sur la galerie publique, en
conservant PhotoSwipe pour la visionneuse. Aucune modification du panel admin
ni du dashboard party.

## Périmètre

Dans le périmètre :

- refonte visuelle complète de `party/gallery` et de sa mise en page ;
- chargement des photos au scroll, en remplacement de la pagination ;
- mode sélection et téléchargement d'un ZIP de la sélection ;
- refonte du pied de page public ;
- bascule de `resources/css/app.css` sur le thème clair.

Hors périmètre :

- les colonnes de métadonnées d'événement (titre affiché, date, lieu, photo de
  couverture) et leur édition dans l'admin : les valeurs sont en dur dans cette
  itération, traitées dans un chantier suivant ;
- le choix de la photo de couverture par l'hôte ;
- le partage d'une sélection par lien ;
- `resources/css/admin.css` et tout ce qui vit sous `pages/admin`.

## Écart entre la maquette et l'existant

| Aspect | Existant | Maquette |
| --- | --- | --- |
| Thème | Sombre, fond Partiful, primaire `#ab76f6`, Syne / Space Grotesk | Clair chaud `#f6f5f2` / `#171512`, Instrument Serif / Instrument Sans |
| En-tête | Navbar fixe, titre « PhotoBooth » | Hero 62vh : couverture, dégradé, titre, date · lieu |
| Grille | Flex, largeurs fixes 144 / 240 px, ratio natif | Grid `auto-fill minmax(min(46%, 210px), 1fr)`, carrés recadrés |
| Chargement | Pagination 30 par page, prefetch des pages voisines | Lots successifs au scroll, skeletons, « Fin de la galerie » |
| Barre collante | Aucune | Compteur de photos + entrée en mode sélection |
| Sélection | Aucune | Voile, badge, animation vers une pile, barre basse, ZIP, toast |
| Pied de page | Logo, liens inertes, bouton « Louez moi » | Logo, CTA devis, réseaux sociaux, liens légaux, ligne légale |
| Visionneuse | PhotoSwipe avec bouton de téléchargement | Identique |

Le recadrage carré est un choix assumé de la maquette : il fait perdre le
cadrage d'origine dans la grille, que la lightbox restitue en pleine résolution.

## Architecture front

Les composants publics doivent vivre sous `resources/js/components/party/` :
`app.css` ne scanne que `pages/party`, `components/party`, `party-layout.tsx` et
`hooks/use-photoswipe.ts`. Un composant placé ailleurs perdrait ses styles.

```
pages/party/gallery.tsx        orchestration : props, état de sélection, sections
components/party/
  gallery-hero.tsx             couverture, dégradé, titre, date · lieu
  gallery-sticky-bar.tsx       compteur, « Sélectionner » / « Tout sélectionner » / « Annuler »
  gallery-grid.tsx             grille carrée, <InfiniteScroll>, skeletons, fin de galerie
  photo-tile.tsx               une vignette : recadrage carré, voile, badge, placeholder
  selection-bar.tsx            barre basse : pile, compteur, formulaire de téléchargement
  gallery-toast.tsx            toast unique piloté par un message
  footer.tsx                   refonte selon la maquette
hooks/
  use-photo-selection.ts       selected / order / landed, entrée-sortie, tout (dé)sélectionner
  use-fly-to-stack.ts          animation Web Animations API vers la pile
  use-photoswipe.ts            existant, étendu d'un drapeau de neutralisation
```

`layouts/party-layout.tsx` perd la `Navbar`, absente de la maquette, et rend le
nouveau `Footer`. `components/party/navbar.tsx`, `primary-button.tsx` et
`picture-card.tsx` n'ont alors plus aucun importateur et sont supprimés.

Chaque composant reçoit ses données en props et ne lit aucun état global :
`gallery.tsx` détient l'état de sélection via `use-photo-selection` et le
distribue. `photo-tile` ignore tout de la sélection au-delà des deux booléens
`selectMode` et `selected`.

## Chargement au scroll

Côté serveur, `PartyController::show` renvoie :

```php
'pictures' => Inertia::scroll(
    fn () => PictureResource::collection(
        $party->pictures()->latest()->paginate(self::PICTURES_PER_PAGE)
    )
),
```

`PICTURES_PER_PAGE` passe de 30 à 24. Quand `hidePictures()` est vrai, la
requête est remplacée par un paginator vide, de sorte que la forme de la prop ne
change pas selon l'état.

Côté client, `gallery-grid.tsx` enveloppe la grille dans
`<InfiniteScroll data="pictures" onlyNext>` de `@inertiajs/react`. Inertia gère
l'IntersectionObserver, le merge des pages, l'état de chargement et la fin de
liste. Les slots `loading` et `end` rendent respectivement les quatre skeletons
shimmer et la mention « Fin de la galerie » de la maquette. `onlyNext` suffit :
la galerie se lit toujours du haut.

Conséquence de forme : la prop `pictures` devient un objet `{ data: [...] }`.
Les assertions existantes de `tests/Feature/GalleryTest.php` qui visent
`pictures.0.url` ou `has('pictures', 3)` sont réécrites en `pictures.data.*`.

PhotoSwipe 5 interroge le DOM de la galerie à l'ouverture et non à
l'initialisation : les photos ajoutées par le scroll entrent dans la visionneuse
sans réinitialisation du lightbox.

## Sélection

`use-photo-selection` détient trois structures, reprises de la maquette :

- `selected` : identifiant → booléen ;
- `order` : ordre de sélection, qui détermine les quatre vignettes de la pile ;
- `landed` : identifiants dont l'animation vers la pile est terminée, pour que
  la vignette n'apparaisse dans la pile qu'à l'atterrissage.

« Tout sélectionner » ne porte que sur les photos déjà chargées, conformément à
la maquette ; le libellé bascule en « Tout désélectionner » quand elles le sont
toutes. « Annuler » vide les trois structures et sort du mode.

En mode sélection, un écouteur de clic en phase de capture posé sur la grille
intercepte le clic avant PhotoSwipe et le convertit en (dé)sélection. Hors mode
sélection, la lightbox se comporte comme aujourd'hui.

`use-fly-to-stack` reproduit l'animation de la maquette avec la Web Animations
API : un clone en `position: fixed` part de la vignette, décrit une courbe vers
la pile, puis la pile encaisse un rebond. L'animation est court-circuitée sous
`prefers-reduced-motion: reduce`, la vignette rejoignant la pile immédiatement.

## Téléchargement de la sélection

Nouvelle route publique, dans le groupe `party.` :

| Route | Nom | Rôle |
| --- | --- | --- |
| `POST /{party}/download` | `party.download` | Renvoie un ZIP des photos sélectionnées |

Protégée par `throttle:10,1`. `DownloadPicturesRequest` valide :

- `ids` : tableau requis, de 1 à `config('party.download.max_pictures')` entrées ;
- `ids.*` : entier existant dans `pictures` **et rattaché à la party courante**,
  ce qui interdit d'aspirer les photos d'une autre soirée en forgeant la requête.

La requête échoue en 403 si `$party->hidePictures()` est vrai : une galerie
expirée ne se télécharge pas davantage qu'elle ne s'affiche.

La réponse est une `StreamedResponse` construite avec `maennchen/zipstream-php`
— nouvelle dépendance Composer — qui lit chaque original depuis S3 via
`Storage::disk('s3')->readStream()` et l'écrit dans le flux de sortie. Aucun
fichier temporaire, consommation mémoire constante quel que soit le nombre de
photos. Le fichier est nommé `{party}-photos.zip`, chaque entrée
`photo-{n}.jpg`.

Une sélection d'une seule photo produit également un ZIP : une seule branche de
code côté serveur. Seul le libellé du bouton change côté client
(« Télécharger » plutôt que « Télécharger le ZIP »).

Côté client, le bouton est le `submit` d'un `<form method="POST">` natif portant
les identifiants en champs cachés et le jeton CSRF. Ce choix est délibéré face à
un `fetch` suivi d'un `URL.createObjectURL` : ce dernier bufferise l'intégralité
du ZIP en mémoire dans l'onglet, ce qui est intenable pour trois cents photos,
alors que la soumission native laisse le navigateur consommer le flux.

Les erreurs de validation remontent dans le toast via les `errors` d'Inertia.

## Configuration

`config/party.php` gagne un bloc :

```php
'download' => [
    'max_pictures' => (int) env('PARTY_DOWNLOAD_MAX_PICTURES', 300),
],
```

Les données du pied de page (URLs des réseaux sociaux, liens légaux, raison
sociale, SIRET) restent en dur dans `components/party/footer.tsx` pour cette
itération.

## Thème

`resources/css/app.css` bascule sur les valeurs de la maquette. Le bloc
`@theme` expose :

| Token | Valeur | Usage |
| --- | --- | --- |
| `--color-surface` | `#f6f5f2` | fond de page |
| `--color-ink` | `#171512` | texte principal, barre basse |
| `--color-cream` | `#fdfcfa` | texte sur fond sombre |
| `--color-muted` | `#8a847c` | libellés secondaires |
| `--color-line` | `rgba(23,21,18,.09)` | séparateurs |
| `--color-placeholder` | `#e8e5df` | fond de vignette en attente |
| `--font-serif` | `'Instrument Serif', Georgia, serif` | titres, compteurs |
| `--font-sans` | `'Instrument Sans', Helvetica, Arial, sans-serif` | corps |

Les deux polices remplacent les imports Google Fonts de Syne et Space Grotesk.
La règle `body { background: url(assets.getpartiful.com…) }` et les tokens
`--color-primary` / `--color-secondary` disparaissent, de même que
`--font-syne`. `admin.css` n'est pas modifié : la séparation des bundles est
déjà couverte par `tests/Feature/AssetBundlingTest.php`.

## Contenu en dur du hero

`gallery-hero.tsx` accepte `title`, `subtitle` et `coverUrl` en props, avec des
valeurs par défaut en dur. Le branchement futur sur de vraies colonnes se
limitera à alimenter ces props depuis `PartyController`.

La couverture est le motif rayé de la maquette dans tous les cas, y compris
lorsque la party a des photos : aucune photo de la galerie n'est promue en
couverture tant que les colonnes de métadonnées n'existent pas.

## États limites

| État | Rendu |
| --- | --- |
| Galerie expirée (`hidePictures`) | Hero et pied de page conservés ; à la place de la grille, le message existant invitant à contacter l'organisateur. Barre collante et mode sélection masqués. |
| Aucune photo | Hero et pied de page conservés ; message existant invitant à aller au photobooth. Compteur à zéro, bouton « Sélectionner » masqué. |
| Vignette sans miniature | Bloc `--color-placeholder` animé au ratio carré, avec `aria-label`, comme le fait déjà `picture-card`. |
| Sélection au-delà du plafond | Erreur de validation affichée dans le toast, aucune redirection. |

## Tests

Chaque changement est couvert. Les tests navigateur de `tests/Web/` sont
connus pour flotter sur des délais de 5 s : les relancer avant d'enquêter.

`tests/Feature/GalleryTest.php` — mis à jour :

- assertions migrées de `pictures.*` vers `pictures.data.*` ;
- la première page contient 24 photos, la seconde le reste ;
- une galerie expirée renvoie une collection vide et `hidePictures` à vrai.

`tests/Feature/PictureDownloadTest.php` — nouveau :

- une sélection valide renvoie 200 avec `Content-Type: application/zip` et un
  `Content-Disposition` portant le nom de la party ;
- l'archive contient une entrée par photo demandée ;
- un identifiant appartenant à une autre party échoue en validation ;
- une sélection dépassant `max_pictures` échoue en validation ;
- une party expirée renvoie 403 ;
- au-delà de dix requêtes par minute, 429.

`tests/Web/GalleryTest.php` — mis à jour :

- les textes attendus suivent la nouvelle maquette ;
- « Sélectionner » fait apparaître les badges et la barre basse ;
- cliquer une vignette en mode sélection incrémente le compteur ;
- « Annuler » referme la barre et vide la sélection ;
- faire défiler jusqu'au bas charge un second lot de photos.

`tests/Feature/AssetBundlingTest.php` : relancé tel quel pour vérifier que la
galerie ne tire toujours pas `admin.css`.

## Séquence de mise en œuvre

1. Thème `app.css` et polices.
2. `Inertia::scroll` côté contrôleur, `<InfiniteScroll>` côté grille, tests
   `Feature/GalleryTest` mis à jour.
3. Hero, barre collante, grille carrée, pied de page.
4. Mode sélection, pile, animation, toast.
5. Route et contrôleur de téléchargement, `zipstream-php`, tests dédiés.
6. Suppression de `navbar.tsx`, `primary-button.tsx`, `picture-card.tsx`.
7. Tests navigateur mis à jour, `pint`, suite complète.
