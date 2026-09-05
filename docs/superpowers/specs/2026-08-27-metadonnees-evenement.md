# Métadonnées d'événement

Date : 2026-08-27

## Contexte

La refonte de la galerie publique
(`docs/superpowers/specs/2026-08-27-galerie-publique-refonte.md`) a laissé le
hero en dur : `gallery-hero.tsx` porte un titre `Camille & Antoine`, un
sous-titre `Samedi 12 septembre 2026 · Domaine des Ormes` et un motif rayé
étiqueté « photo de couverture ». Les colonnes correspondantes n'existent pas
en base, et rien ne permet à l'administration de les renseigner.

## Objectif

Donner à chaque party un titre affiché, une description et une bannière,
réglables depuis l'administration, et les brancher sur le hero de la galerie
publique. La bannière est supprimable, auquel cas le hero retombe sur le motif
rayé existant.

## Périmètre

Dans le périmètre :

- trois colonnes sur `parties` : `display_name`, `description`, `banner_path` ;
- upload, remplacement et suppression de la bannière depuis
  `admin/parties/edit` ;
- exposition des trois valeurs par `PartyResource`, consommées par le hero.

Hors périmètre :

- le réglage de ces valeurs par l'hôte depuis `/{party}/dashboard`, qui reste
  la page vide qu'elle est aujourd'hui ;
- la promotion d'une photo de la galerie en bannière ;
- toute date ou lieu structuré : la description est un texte libre d'une ligne.

Le `name` n'est pas touché. Il est déjà unique, sert de route key et de
sous-domaine, et s'édite déjà dans l'administration.

## Données

Migration `add_metadata_columns_to_parties_table` :

| Colonne | Type | Rôle |
| --- | --- | --- |
| `display_name` | `string` nullable, après `name` | Titre affiché dans le hero |
| `description` | `string` nullable | Ligne de sous-titre du hero |
| `banner_path` | `string` nullable | Chemin S3 de la bannière |

Aucune valeur par défaut n'est écrite à la migration : une party sans
`display_name` affiche son `name`, ce qui rend la rétro-remplissage inutile.

Sur `Party`, `display_name` et `description` rejoignent `$fillable`.
`banner_path` en reste absent : seul `PartyBannerStorage` l'écrit, ce qui
interdit qu'une soumission de formulaire pointe la bannière sur un chemin
arbitraire. Deux méthodes s'ajoutent :

- `displayTitle(): string` renvoie `$this->display_name ?: $this->name` ;
- `bannerUrl(): ?string` renvoie l'URL S3, ou `null` sans bannière, sur le
  modèle de `Picture::thumbnailUrl()`.

Un hook `deleted` supprime le fichier de bannière quand la party disparaît,
comme `Picture` le fait déjà pour ses propres chemins.

## Stockage de la bannière

Nouveau service `app/Services/PartyBannerStorage.php`, deux méthodes publiques :

- `store(Party $party, UploadedFile $file): void` — lit le fichier avec
  Intervention, `scaleDown(width: self::MAX_WIDTH)`, `toJpeg(quality: 82)`,
  écrit sous `banners/{party->id}/{uuid}.jpg`, supprime l'éventuel fichier
  précédent, puis met `banner_path` à jour ;
- `remove(Party $party): void` — supprime le fichier et remet `banner_path` à
  `null`.

`MAX_WIDTH` vaut 2000. Le hero est large d'au plus 1440 CSS px, donc 2000
couvre les écrans à densité double sans conserver l'original d'un téléphone.

Le redimensionnement est synchrone, contrairement aux miniatures de photos qui
passent par `CreatePictureThumbnail`. L'administration n'envoie qu'un fichier à
la fois et l'attente d'une seconde y est acceptable ; en échange, la galerie
n'a jamais d'état intermédiaire à rendre entre l'upload et la fin d'un job.

Le nom de fichier en UUID à chaque upload garantit qu'un remplacement change
l'URL, sans quoi un cache navigateur ou un CDN servirait l'ancienne image.

## Administration

`UpdateAdminPartyRequest` gagne quatre règles, en tableau comme les autres :

- `display_name` : `nullable`, `string`, `max:255` ;
- `description` : `nullable`, `string`, `max:255` ;
- `banner` : `nullable`, `image`, `max:10240`, le même plafond que les photos
  de `StoreAdminPicturesRequest` ;
- `remove_banner` : `nullable`, `boolean`.

Les messages d'erreur associés sont en anglais, comme le reste du fichier.

`PartyController::update` écrit les colonnes texte via
`Arr::only($request->validated(), ['name', 'user_id', 'expire_at', 'display_name', 'description'])`.
Les clés absentes de la requête ne figurent pas dans `validated()` et ne sont
donc pas écrasées : l'édition en ligne de `admin/parties/index`, qui ne poste
que `name`, `user_id` et `expire_at`, ne vide pas les métadonnées.

Vient ensuite la bannière : si `remove_banner` est vrai, `remove()` ; sinon, si
un fichier `banner` est présent, `store()`. La suppression l'emporte sur
l'upload, de sorte que deux intentions contradictoires dans une même
soumission aient une issue déterminée.

`edit` expose `display_name`, `description` et `banner_url` en plus des
valeurs actuelles.

`StorePartyRequest` et le formulaire de création restent inchangés : les
métadonnées se règlent après création, la création restant l'opération de masse
qu'elle est aujourd'hui.

### Formulaire

`resources/js/pages/admin/parties/edit.tsx` gagne deux `Input` texte, un champ
fichier, un aperçu de la bannière courante et une case « Remove banner »
affichée uniquement quand `banner_url` est non nul.

La soumission passe par
`form.transform((data) => ({ ...data, _method: 'put', expire_at: fromDateTimeLocalValue(data.expire_at) }))`
puis `form.post(...)`. Inertia bascule seul en `FormData` dès qu'un champ porte
un fichier, et `_method` porte le PUT que le multipart ne sait pas exprimer.

Ce fichier vit sous `pages/admin` et relève donc de `admin.css` et de shadcn ;
les composants publics ne sont pas concernés.

## Galerie publique

`PartyResource` expose `display_name`, `description` et `banner_url`. La page
`party/gallery` reçoit déjà `party` par cette ressource : le contrôleur public
n'a rien à ajouter, et l'API versionnée hérite des mêmes champs.

`gallery.tsx` passe au hero `title={party.display_name ?? party.name}`,
`subtitle={party.description}` et `bannerUrl={party.banner_url}`, et donne le
même titre affiché à `<Head>`, qui rendait jusqu'ici le `name`.

`gallery-hero.tsx` perd `DEFAULT_TITLE`, `DEFAULT_SUBTITLE` et le label
« photo de couverture », vestige de la maquette. Avec bannière, un `<img>` en
`object-cover` occupe `absolute inset-0` sous le dégradé existant, avec
`fetchpriority="high"` puisque c'est la plus grande image du premier écran, et
`alt=""` : l'image est décorative, le titre qu'elle porte est déjà dans le
`<h1>` voisin. Sans bannière, le motif rayé actuel est conservé tel quel. La
ligne de sous-titre n'est rendue que si la description est non vide.

## États limites

| État | Rendu |
| --- | --- |
| `display_name` vide | Le hero et l'onglet affichent le `name` |
| `description` vide | La ligne de sous-titre n'est pas rendue |
| Aucune bannière | Motif rayé actuel, sans le label de la maquette |
| Bannière remplacée | L'ancien fichier est supprimé de S3, l'URL change |
| Party supprimée | Le fichier de bannière est supprimé |

## Tests

`tests/Feature/AdminPartiesTest.php` — étendu :

- la mise à jour renseigne `display_name` et `description` ;
- un upload de bannière écrit un fichier sur le disque `s3` simulé et
  renseigne `banner_path` ;
- un remplacement supprime le fichier précédent ;
- `remove_banner` vide la colonne et supprime le fichier ;
- une description de plus de 255 caractères est rejetée ;
- un fichier qui n'est pas une image est rejeté ;
- une soumission sans les nouveaux champs, comme celle de l'édition en ligne,
  laisse les métadonnées existantes intactes.

`tests/Feature/PartyTest.php` — étendu :

- `displayTitle()` renvoie le `display_name`, et le `name` à défaut ;
- `bannerUrl()` renvoie `null` sans bannière ;
- supprimer une party supprime son fichier de bannière.

`tests/Feature/GalleryTest.php` — étendu : la prop `party` porte
`display_name`, `description` et `banner_url`.

`tests/Web/GalleryTest.php` — étendu :

- le hero affiche le display name et la description ;
- sans display name, le hero affiche le `name` ;
- sans bannière, le hero ne contient pas d'image.

`database/factories/PartyFactory.php` gagne les trois attributs à `null` et un
état `withBanner()`.

Les tests navigateur de `tests/Web/` sont connus pour flotter sur des délais de
5 s : les relancer avant d'enquêter.

## Séquence de mise en œuvre

1. Migration, colonnes du modèle, méthodes `displayTitle` / `bannerUrl`, hook
   de suppression, factory, tests de modèle.
2. `PartyBannerStorage` et ses tests.
3. Form Request, contrôleur admin, tests de fonctionnalité.
4. Formulaire `admin/parties/edit`.
5. `PartyResource`, hero et page publique, tests de props et navigateur.
6. `pint`, suite complète.
