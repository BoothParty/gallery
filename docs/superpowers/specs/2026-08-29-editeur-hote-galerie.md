# Éditeur de galerie pour l'hôte

Date : 2026-08-29

## Contexte

Les métadonnées d'événement
(`docs/superpowers/specs/2026-08-27-metadonnees-evenement.md`) ont donné à
chaque party un `display_name`, une `description` et un `banner_path`, mais
elles ne se règlent que depuis l'administration. L'hôte d'un événement n'a
aujourd'hui aucun moyen de corriger le titre de sa galerie ou d'y poser une
photo de couverture : il doit passer par nous.

La maquette Claude Design `Galerie Photobooth.dc.html` (projet
`96f9683b-4bd3-4cb2-b67c-fe90f366f25c`) décrit l'interface visée : un bouton
« Modifier la galerie » en surimpression du hero, visible seulement pour qui
détient un lien d'édition, et une modale portant le nom, la description et un
cadre de recadrage de la couverture.

## Objectif

Permettre à l'hôte, muni d'un lien porteur d'un token, de régler depuis la
galerie publique elle-même son titre, sa description et sa photo de couverture,
cadrage compris, sans compte ni connexion.

## Périmètre

Dans le périmètre :

- une colonne `host_token` sur `parties`, et le lien d'édition qu'elle compose ;
- trois colonnes de cadrage de bannière, appliquées par le hero ;
- l'ouverture d'une session d'hôte par ce lien, et son nettoyage de l'URL ;
- un endpoint public d'écriture protégé par cette session ;
- la modale d'édition et son recadreur, dans le bundle public ;
- l'affichage et la régénération du lien depuis l'administration.

Hors périmètre :

- la promotion d'une photo de la galerie en couverture : la maquette n'offre
  que l'import d'un fichier, et l'état `pickerOpen` qu'elle porte reste mort ;
- toute expiration du lien d'édition : il vaut tant qu'on ne le régénère pas ;
- l'envoi du lien à l'hôte par mail ou SMS, qui reste manuel ;
- `/{party}/dashboard`, qui reste ce qu'elle est.

Le `name` n'est pas éditable par l'hôte. Il est la route key et le
sous-domaine ; le changer casserait les QR codes déjà imprimés.

## Données

### `add_host_token_to_parties_table`

| Colonne | Type | Rôle |
| --- | --- | --- |
| `host_token` | `string(64)` nullable, unique, après `name` | Secret du lien d'édition |

Le token est stocké **en clair**, contrairement à un mot de passe : l'administration
doit pouvoir réafficher le lien complet à n'importe quel moment pour le
renvoyer à l'hôte, ce qu'un hash interdirait. Le risque est celui d'une fuite
de base, et il porte alors sur le droit de renommer une galerie — pas sur des
photos, qui sont de toute façon publiques.

La migration remplit les parties existantes. Un hook `creating` sur `Party` en
pose un à chaque création, de sorte que la création en masse depuis Notion en
bénéficie sans y toucher. La valeur est `Str::random(48)` : la colonne est
dimensionnée à 64 pour laisser de la marge à un format ultérieur.

`host_token` reste hors de `$fillable`, comme `banner_path` : seul un
`forceFill` l'écrit, ce qui interdit qu'une soumission de formulaire le fixe à
une valeur choisie.

### `add_banner_framing_to_parties_table`

| Colonne | Type | Défaut |
| --- | --- | --- |
| `banner_position_x` | `decimal(5,2)` | `50` |
| `banner_position_y` | `decimal(5,2)` | `50` |
| `banner_zoom` | `decimal(4,2)` | `1` |

Castées en `float`. Elles portent un défaut plutôt que d'être nullable :
« aucun cadrage choisi » et « cadrage centré sans zoom » rendent exactement la
même image, et un défaut évite un `?? 50` chez chaque consommateur. Elles
rejoignent `$fillable` : contrairement au chemin de la bannière, ce sont des
nombres bornés par la validation, sans capacité de nuire.

Le cadrage est **purement CSS** : la bannière est stockée une fois, telle que
`PartyBannerStorage` la produit déjà, et le hero la positionne. Aucun dérivé
n'est découpé. L'hôte peut donc rouvrir l'éditeur et recadrer indéfiniment sans
perte, et le cadrage s'adapte de lui-même quand le ratio du hero change d'un
écran à l'autre. Le coût est que le visiteur télécharge la bannière entière,
soit au plus les 2000 px que `PartyBannerStorage::MAX_WIDTH` garantit.

### Méthodes de `Party`

`hostEditUrl(): string` compose `publicUrl().'?token='.$this->host_token`.
C'est le seul endroit qui connaît la forme du lien.

## Accès de l'hôte

### `ResolvePartyHost`

Middleware appliqué à la route `party.gallery`. Quand la requête porte un
paramètre `token` :

1. il le compare au `host_token` de la party avec `hash_equals` ;
2. si les deux coïncident, il pose `party_host.{party->id}` à `true` en
   session ;
3. **dans les deux cas** il redirige vers la galerie sans le paramètre.

La redirection systématique est délibérée : un token juste et un token faux
produisent la même réponse observable, ce qui prive un attaquant de l'oracle
dont il aurait besoin pour deviner un token par essais successifs. Elle sort
aussi le secret de la barre d'adresse, de l'historique, des en-têtes `Referer`
sortants et de tout copier-coller d'URL que l'hôte ferait vers ses invités.

La session est celle du domaine de la galerie, déjà ouverte par la pile `web`.
Le droit se perd si l'hôte change de navigateur, auquel cas il rouvre son lien.

### `EnsurePartyHost`

Middleware aliasé `party.host` dans `bootstrap/app.php`. Il lit
`party_host.{party->id}` en session et renvoie 403 quand il est absent. Il
protège l'endpoint d'écriture, jamais la lecture.

### Droit d'édition et expiration

Une party expirée cache ses photos mais reste éditable par son hôte : c'est sa
galerie, et corriger un titre après coup est légitime. `canEdit` ne consulte
donc pas `hidePictures()`.

## Écriture

```
POST /{party}/metadata   → party.metadata.update
     middleware : party.host, throttle:party-metadata
```

`POST` plutôt que `PUT` parce que la bannière voyage en multipart, qu'un `PUT`
ne sait pas transporter ; le front y ajoute `_method: 'put'`.

Le limiteur `party-metadata` rejoint `party-download` et `party-picture` dans
`AppServiceProvider`, à 20 par minute par `ip:party`. Une édition est un geste
rare, et le plafond n'existe que pour borner l'abus.

### `UpdatePartyMetadataRequest`

Règles en tableau, comme ses voisines :

| Champ | Règles |
| --- | --- |
| `display_name` | `nullable`, `string`, `max:60` |
| `description` | `nullable`, `string`, `max:90` |
| `banner` | `nullable`, `image`, `max:10240` |
| `remove_banner` | `nullable`, `boolean` |
| `banner_position_x` | `required`, `numeric`, `between:0,100` |
| `banner_position_y` | `required`, `numeric`, `between:0,100` |
| `banner_zoom` | `required`, `numeric`, `between:1,3` |

Les plafonds de 60 et 90 caractères sont ceux des champs de la maquette, et
non les 255 que `UpdateAdminPartyRequest` autorise. L'écart est voulu : au-delà,
le titre en serif du hero et sa ligne de sous-titre en petites capitales
débordent. L'administration garde sa marge parce qu'elle sait ce qu'elle fait.

`authorize()` renvoie `true` : l'autorisation est le fait de `party.host`, pas
de la Form Request, comme le veut la convention du fichier voisin.

Les messages d'erreur sont en **français**, contrairement à ceux de
`UpdateAdminPartyRequest` : ceux-ci sont lus par l'hôte dans une interface
entièrement française, pas par nous dans l'administration.

### `PartyMetadataController`

Contrôleur à action unique, en `__invoke`, comme `PictureDownloadController` et
`PictureFileController` que la route publique référence déjà par leur seul nom
de classe. Il :

1. écrit `display_name`, `description` et les trois colonnes de cadrage depuis
   `validated()` ;
2. si `remove_banner` est vrai, `PartyBannerStorage::remove()` **et** remise du
   cadrage à 50/50/1 — une couverture absente n'a pas de cadrage à conserver ;
3. sinon, si un fichier `banner` est présent,
   `PartyBannerStorage::store()`.

La suppression l'emporte sur l'upload, exactement comme dans
`Admin\PartyController::update`, pour que deux intentions contradictoires dans
une même soumission aient une issue déterminée.

La réponse est un `back()` : Inertia recharge la page de galerie, dont la prop
`party` porte les nouvelles valeurs.

## Galerie publique

`PartyController::show` expose une prop `canEdit`, lue en session. `PartyResource`
expose `banner_position_x`, `banner_position_y` et `banner_zoom` en plus des
champs actuels ; l'API versionnée en hérite sans travail.

Tous les composants publics vivent sous `resources/js/components/party/` et
n'utilisent que Tailwind sur les tokens existants (`--color-ink`,
`--color-shade`, `text-cream`, `bg-placeholder`, `font-serif`). Ni shadcn ni
Radix : ces deux-là ne sont compilés que dans `admin.css`, et un composant
public qui s'en sert perd ses styles.

### `gallery-hero.tsx`

Reçoit `position: { x: number; y: number }` et `zoom: number`, appliqués au
`<img>` déjà présent en `objectPosition: '{x}% {y}%'` et
`transform: scale({zoom})`. Reçoit aussi `canEdit` et `onEdit`, et rend quand
`canEdit` le bouton pilule en surimpression décrit par la maquette : bordure
translucide, fond sombre à `backdrop-filter: blur(10px)`, icône crayon, en haut
à droite au-dessus du dégradé.

### `gallery-editor.tsx`

La modale : scrim opaque cliquable pour fermer, panneau de `min(620px, 100vw -
24px)` centré, en-tête avec titre serif et bouton de fermeture rond, corps
défilant, pied `Annuler` / `Enregistrer`. Ouverture et fermeture par transition
d'opacité et d'échelle, sans démontage, comme la maquette.

Elle porte le `useForm` d'Inertia — `forceFormData`, `_method: 'put'`,
`preserveScroll` — et son état de brouillon. `Annuler` et le scrim jettent le
brouillon sans écrire. Au succès, la modale se ferme et le `GalleryToast` déjà
en place affiche « Galerie mise à jour ». Les erreurs de validation
s'affichent sous leur champ.

Le champ description porte le compteur de caractères restants de la maquette.

### `cover-cropper.tsx` et `use-cover-crop.ts`

Le cadre est en `aspect-ratio: 16 / 7`, `overflow: hidden`, `touch-action:
none`, avec la grille des tiers en surimpression, le label « Zone visible » et,
sans couverture, le texte « Aucune photo de couverture ». Dessous, le slider de
zoom de 1 à 3 par pas de 0,02, un bouton « Recadrer » qui remet 50/50/1, et un
label-bouton « Importer une photo » masquant un `<input type="file"
accept="image/*">`.

Le cadre 16:7 ne reproduit pas le hero, qui fait `62vh` borné à 340–560 px :
le cadrage montré est indicatif, ce que le label « Zone visible » dit déjà. On
ne cherche pas à faire coïncider les deux ratios.

**Le déplacement** est calculé, pas approximé. La maquette multiplie le
déplacement du pointeur par un facteur 1.6 arbitraire, si bien que l'image
glisse sous le doigt. Le hook part des dimensions naturelles de l'image, dont
il déduit la taille rendue en `cover` puis le débord dans chaque axe
(`renduLargeur * zoom - cadreLargeur`). Un déplacement de `dx` pixels vaut
alors `-dx / débord * 100` points de pourcentage, ce qui fait suivre l'image
exactement sous le pointeur, et le clamp à 0–100 tombe précisément aux bords
de l'image. Quand un axe n'a aucun débord, il reste figé à 50.

Le glissement passe par les événements *pointer* et `setPointerCapture`, ce qui
couvre souris et tactile sans code séparé, et survit à un pointeur sorti du
cadre.

Un fichier fraîchement importé s'affiche via `URL.createObjectURL`, révoqué au
démontage, et remet le cadrage à 50/50/1 : le cadrage de l'image précédente n'a
aucun sens sur la nouvelle.

## Administration

Il n'existe pas de page `admin/parties/edit` : une party s'édite dans la modale
« Party details » de `resources/js/pages/admin/parties/index.tsx`, alimentée par
la prop `editableParties`. Celle-ci gagne un champ `host_edit_url`, et la modale
un bloc « Host edit link » : l'URL complète en champ lecture seule, un bouton de
copie et un bouton **Regenerate**.

```
POST admin/parties/{party}/host-token   → admin.parties.host-token
```

La régénération écrase `host_token` et invalide donc l'ancien lien ; elle
demande confirmation, puisque le lien déjà envoyé à l'hôte cessera de
fonctionner. Les sessions d'hôte déjà ouvertes, elles, survivent : elles ne
portent qu'un booléen. C'est acceptable — la régénération sert à couper un lien
qui a fuité vers un tiers, pas à expulser un hôte de son propre navigateur.

Ce fichier vit sous `pages/admin` et relève donc de shadcn et d'`admin.css`. Il
est écrit en guillemets doubles et sans point-virgule, contrairement aux
composants publics : suivre le style local du fichier.

## États limites

| État | Rendu |
| --- | --- |
| Lien sans token | Galerie normale, aucun bouton d'édition |
| Token erroné | Redirection vers l'URL propre, aucun droit, aucune indication |
| Token juste, galerie expirée | Le bouton d'édition est là, les photos restent cachées |
| Session d'hôte, mais token régénéré | La session porte encore le droit, jusqu'à sa fin |
| Écriture sans session d'hôte | 403 |
| Couverture absente | Motif rayé actuel, cadre d'édition vide et non déplaçable |
| Couverture supprimée | Cadrage remis à 50/50/1 |
| Couverture remplacée | Ancien fichier supprimé de S3, URL changée, cadrage remis à 50/50/1 |
| Image sans débord sur un axe | L'axe reste à 50, le déplacement n'agit que sur l'autre |
| Titre vidé par l'hôte | Le hero retombe sur le `name`, comme aujourd'hui |

## Tests

`tests/Feature/PartyHostAccessTest.php` — nouveau :

- un token juste pose la session et redirige vers l'URL sans paramètre ;
- un token faux redirige de la même façon et ne pose rien ;
- la prop `canEdit` suit la session ;
- une party expirée laisse `canEdit` à `true` pour son hôte.

`tests/Feature/PartyMetadataTest.php` — nouveau :

- l'écriture renseigne `display_name`, `description` et les trois colonnes de
  cadrage ;
- un upload écrit un fichier sur le disque `s3` simulé et renseigne
  `banner_path` ;
- `remove_banner` vide la colonne, supprime le fichier et remet le cadrage à
  50/50/1 ;
- `remove_banner` l'emporte sur un fichier envoyé dans la même soumission ;
- un titre de plus de 60 caractères, une description de plus de 90, un zoom
  hors de 1–3 et une position hors de 0–100 sont rejetés ;
- une écriture sans session d'hôte renvoie 403 ;
- une écriture avec la session d'une **autre** party renvoie 403.

`tests/Feature/PartyTest.php` — étendu : un `host_token` est généré à la
création, il est unique, et `hostEditUrl()` compose l'URL attendue.

`tests/Feature/AdminPartiesTest.php` — étendu : la régénération change le
token, et l'ancien lien cesse d'ouvrir une session d'hôte.

`tests/Feature/GalleryTest.php` — étendu : la prop `party` porte les trois
colonnes de cadrage.

`tests/Web/GalleryEditorTest.php` — nouveau : le bouton est absent sans droit,
présent avec ; la modale s'ouvre et se ferme ; un enregistrement met à jour le
titre du hero.

`database/factories/PartyFactory.php` gagne les trois colonnes de cadrage à
leurs valeurs par défaut.

Les tests navigateur de `tests/Web/` sont connus pour flotter sur des délais de
5 s : les relancer avant d'enquêter.

## Séquence de mise en œuvre

1. Les deux migrations, les colonnes du modèle, le hook `creating`,
   `hostEditUrl()`, la factory et les tests de modèle.
2. `ResolvePartyHost`, `EnsurePartyHost`, leur enregistrement, la prop
   `canEdit`, et `PartyHostAccessTest`.
3. `UpdatePartyMetadataRequest`, `PartyMetadataController`, la route, le
   limiteur, et `PartyMetadataTest`.
4. `PartyResource`, `gallery-hero.tsx` avec cadrage et bouton, tests de props.
5. `use-cover-crop.ts` et `cover-cropper.tsx`.
6. `gallery-editor.tsx` et son branchement dans `pages/party/gallery.tsx`.
7. Le bloc de lien dans `admin/parties/edit`, sa route de régénération et ses
   tests.
8. `tests/Web/GalleryEditorTest.php`.
9. `pint`, suite complète.
