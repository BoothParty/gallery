# Rafraîchissement automatique de la galerie publique

Date : 2026-09-06

## Contexte

La galerie publique `{party}/` charge ses photos une fois, au chargement de la
page, puis n'en apprend plus jamais de nouvelles. Pendant une soirée, les
clichés arrivent pourtant en continu : quelqu'un passe à la borne, sa photo est
en base quelques secondes plus tard, mais l'invité qui a la galerie ouverte ne
la verra qu'en actualisant.

Or les invités d'un mariage ne sont pas un public technique. Ils ne pensent pas
à tirer la page vers le bas, et l'écran vide de début de soirée — celui qui dit
aujourd'hui « rafraîchissez la page pour les découvrir » — leur laisse croire
que la borne ne marche pas.

Deux autres changements sont aujourd'hui tout aussi invisibles :

- l'organisateur supprime des photos depuis `/{party}/dashboard` pendant la
  soirée (`AdminGalleryController::bulkDelete`) ;
- les vignettes arrivent en différé. `CreatePictureThumbnail` écrit
  `thumbnail_path` après coup, et jusque-là la tuile affiche un placeholder qui
  ne se remplit jamais tout seul.

## Objectif

Que la galerie publique se mette à jour d'elle-même quand son contenu change,
sans que le visiteur ait quoi que ce soit à faire, et sans jamais bouger la page
sous ses doigts pendant qu'il s'en sert.

## Périmètre

Dans le périmètre :

- un service qui calcule l'état des photos d'une party ;
- un endpoint public de sondage, stateless, caché et throttlé ;
- un index composite sur `pictures` ;
- un hook client qui possède la cadence de sondage ;
- le rafraîchissement silencieux, et la pastille qui le remplace quand le
  visiteur est occupé ;
- la reformulation du texte de l'état vide.

Hors périmètre :

- `/{party}/dashboard`, qui reste ce qu'elle est ;
- les WebSockets et tout transport temps réel : le besoin est un délai de
  quelques secondes, pas la milliseconde, et le coût d'exploitation d'un
  serveur de sockets ne se justifie pas ici ;
- toute notification côté organisateur ;
- le rafraîchissement des métadonnées de l'événement (titre, description,
  bannière). Elles ne changent qu'à la main, depuis l'éditeur de l'hôte, qui
  recharge déjà la page en enregistrant.

## Le signal

Un seul couple d'agrégats suffit à couvrir les trois cas :

| Agrégat | Ce qu'il détecte |
| --- | --- |
| `count` — `COUNT(*)` | une photo arrive, une photo est supprimée |
| `last_change` — `MAX(updated_at)` | une photo arrive, une vignette finit de se générer |

`CreatePictureThumbnail` appelle `$picture->update(['thumbnail_path' => …])`,
qui touche `updated_at` : l'arrivée d'une vignette est donc bien un changement
de `last_change`, sans colonne ni événement supplémentaire.

Une suppression ne bouge pas `MAX(updated_at)` — les lignes restantes n'ont pas
changé — mais elle fait baisser `count`. Une suppression suivie d'un ajout dans
le même intervalle laisse `count` identique mais fait avancer `last_change`. Les
deux agrégats se couvrent mutuellement ; aucun des deux ne suffit seul.

### `PartyPicturesState`

Un service `App\Services\PartyPicturesState`, seule source du signal :

```php
public function for(Party $party): array;      // ['count' => int, 'last_change' => ?string]
public function forget(int $partyId): void;    // invalide l'entrée de cache
```

`for()` sert toujours depuis le cache ; il n'existe pas de variante non cachée,
puisque l'entrée n'est jamais périmée (voir « Cache »).

`last_change` est sérialisé en ISO 8601, comme les autres dates exposées par les
resources. Il vaut `null` sur une galerie sans photo.

Une party expirée (`Party::hidePictures()`) renvoie toujours
`['count' => 0, 'last_change' => null]`, sans requête. La galerie n'affiche rien
dans ce cas, et le signal doit dire la même chose que la page : un client resté
ouvert au passage de la date d'expiration ne doit pas se mettre à annoncer des
photos qu'il ne montrera pas.

### `add_party_updated_index_to_pictures_table`

| Index | Colonnes |
| --- | --- |
| `pictures_party_id_updated_at_index` | `party_id`, `updated_at` |

`pictures` ne porte aujourd'hui que l'index de sa clé étrangère. Le `COUNT`
s'appuie déjà sur ce préfixe, mais le `MAX(updated_at)` ferait un parcours de
toutes les lignes de la party. L'index composite résout les deux agrégats sans
toucher aux lignes.

## L'endpoint

`GET /api/parties/{party}/state`, servi par `Api\PartyStateController`
(invokable), monté dans `routes/api.php` hors du groupe Sanctum et nommé
`api.parties.state`.

```json
{ "count": 128, "last_change": "2026-09-06T21:14:03+00:00" }
```

Pas de middleware `ResolvePartyHost` : le signal est le même pour tout le monde,
et l'hôte n'a rien de plus à en apprendre.

**Pourquoi dans les routes d'API et pas dans `web.php`.** L'application tourne
avec `SESSION_DRIVER=database`. Toute route du groupe `web` traverse
`StartSession`, qui écrit la session en base à chaque requête : 480 sondages par
minute deviendraient 480 écritures par minute, soit bien plus cher que l'agrégat
qu'on prend soin de cacher. Le groupe `api` est stateless et porte déjà
`SubstituteBindings`, dont la route a besoin pour résoudre `{party}` par son
`name`. L'URL s'aligne au passage sur `parties/{party}/pictures`, déjà servie
là. La sonde n'est pas une page Inertia ; elle n'a rien à faire dans `web.php`.

### Cache

L'agrégat est mémorisé sous la clé `party:{id}:pictures-state` et **invalidé par
les écritures**, pas par le temps. `Picture` gagne deux hooks dans `booted()` :

```php
static::saved(fn (Picture $picture) => $state->forget($picture->party_id));
static::deleted(fn (Picture $picture) => $state->forget($picture->party_id));
```

C'est ce qui rend le sondage négligeable : 80 téléphones qui sondent toutes les
10 secondes font 480 requêtes HTTP par minute, et **une** requête SQL par photo
qui arrive — de l'ordre de deux par minute au plus fort d'une soirée, zéro quand
rien ne bouge.

Tous les chemins d'écriture passent par des instances de modèle, donc les
événements les voient tous : `$party->pictures()->create()` côté borne et côté
admin, le `$picture->update()` du job vignette, le `->get()->each->delete()` de
`AdminGalleryController::bulkDelete`, et le `$picture->delete()` de l'API. Le
modèle dépend d'ailleurs déjà de ce contrat — `Picture::booting()` efface les
fichiers S3 dans un hook `deleted`. Une écriture qui court-circuiterait les
événements laisserait des orphelins sur S3 bien avant de fausser un compteur.

Un **TTL de garde de 10 minutes** double malgré tout l'invalidation. Pas pour la
fraîcheur, dont les événements se chargent, mais parce que le mode de panne d'un
cache éternel est exactement le bug qu'on corrige : une galerie qui ne se met
plus jamais à jour. Si quelqu'un ajoute un jour une écriture en masse qui saute
les événements, le TTL transforme « figée pour toujours » en « en retard un
instant ».

Le cache n'ajoute donc aucune latence : une photo invalide l'entrée à l'instant
où elle est créée, et le sondage suivant la voit. Le délai d'apparition reste
l'intervalle de sondage, rien de plus.

### Throttle

Un limiteur `party-state`, keyé `ip:party` comme ses trois voisins et pour la
même raison — les invités d'un même lieu partagent le wifi, donc l'adresse.

Le plafond est de **600 requêtes par minute**. Il est haut parce que le bucket
est partagé par tous les téléphones d'une même soirée : 80 appareils à la
cadence la plus rapide en consomment 480. La valeur borne l'abus sans jamais
gêner un événement réel, et l'endpoint qu'elle protège est servi par le cache.

## La sonde, côté client

Un hook `useGalleryChanges` qui possède la cadence et rien d'autre.

```ts
interface GalleryChanges {
    /** Le contenu a bougé depuis la dernière prise en compte. */
    changed: boolean;
    /** Photos apparues depuis, négatif ou nul si le contenu a rétréci. */
    delta: number;
    /** Repart de l'état courant, après un rafraîchissement. */
    acknowledge: () => void;
}

export default function useGalleryChanges(
    partyName: string,
    options: { enabled: boolean; initial: PicturesState },
): GalleryChanges;
```

### La référence initiale

`PartyController::show` expose une prop `picturesState`, servie par le même
`for()` que l'endpoint, qui sert de référence au client.

Le sondage pourrait établir sa propre référence à son premier tick, ce qui
éviterait la prop. Mais une photo arrivée entre le rendu de la page et ce
premier tick serait alors absorbée dans la référence et jamais affichée. La prop
supprime cette fenêtre. Elle lit le cache sans précaution particulière :
l'entrée étant invalidée à l'écriture, la référence est toujours exacte au
moment du rendu, et un chargement de page ne déclenche jamais de
rafraîchissement fantôme au premier tick.

`picturesCount`, qui alimente la barre collante, reste calculé par
`$picturesPage->total()`. Les deux valeurs peuvent diverger de quelques
secondes ; ça n'a aucune conséquence, `picturesState` ne sert qu'à comparer.

### Cadence

Trois paliers, définis dans `config/party.php` sous `refresh` et exposés à la
page comme une prop, pour que les tests puissent les raccourcir :

| Clé de config | Défaut | Quand |
| --- | --- | --- |
| `refresh.hot` | 10 s | galerie vide, ou changement il y a moins de `refresh.warm_after` |
| `refresh.warm` | 30 s | dernier changement entre `warm_after` et `cold_after` |
| `refresh.cold` | 60 s | rien depuis plus de `refresh.cold_after` |
| `refresh.warm_after` | 120 s | seuil chaud → tiède |
| `refresh.cold_after` | 300 s | seuil tiède → froid |
| `refresh.cache_ttl` | 600 s | TTL de garde de l'agrégat, doublé par l'invalidation |

Une galerie vide reste chaude en permanence : c'est le cas cible, l'invité
arrivé avant la première photo.

La sonde s'arrête sur `visibilitychange` quand l'onglet passe en arrière-plan,
et en relance une **immédiatement** au retour au premier plan. C'est le cas réel
dominant — quelqu'un repose son téléphone et le reprend deux minutes plus tard,
et retrouve la page à jour sans rien faire. C'est aussi ce qui évite que 80
téléphones verrouillés tapent le serveur toute la soirée.

Le hook ne sonde pas du tout quand `enabled` est faux, c'est-à-dire sur une
galerie expirée.

### Erreurs

Une requête en échec est avalée en silence : pas de toast, pas de message. Un
invité n'a rien à faire d'une erreur réseau sur une sonde de fond. L'intervalle
double à chaque échec jusqu'au plafond de 60 secondes, et le premier succès
rétablit la cadence normale.

## Le comportement visible

### Le rafraîchissement

```ts
router.reload({ only: ['pictures', 'picturesCount'], reset: ['pictures'] });
```

Le `reset` est indispensable. `pictures` est une prop de scroll fusionnée par
`InfiniteScroll` ; sans lui, la nouvelle page 1 s'empilerait sur la liste déjà
chargée au lieu de la remplacer, et la galerie afficherait chaque photo deux
fois. La grille est triée `latest()`, donc les nouvelles photos arrivent en
haut ; recharger la page 1 les fait entrer par le bon bout.

Un visiteur qui avait déroulé plusieurs pages les perd. C'est acceptable
précisément parce qu'on ne rafraîchit en silence que s'il est resté en haut.

### Silencieux ou pastille

Le rafraîchissement est silencieux quand les trois conditions sont réunies :

1. la page est près du haut (moins de 200 px de défilement) ;
2. le mode sélection est inactif ;
3. la lightbox est fermée.

Sinon, une pastille apparaît sous la barre collante : « 3 nouvelles photos ».
Au tap, elle remonte la page en haut puis rafraîchit. Rien ne bouge jamais sous
les doigts de quelqu'un qui sélectionne, qui regarde une photo en plein écran,
ou qui a déroulé la galerie.

La pastille n'apparaît que sur un changement détecté. Quand ce changement ne
se traduit pas par des photos en plus — l'organisateur en a supprimé, ou une
vignette est arrivée sans nouveau cliché, donc un delta négatif ou nul — elle
dit « Galerie mise à jour » plutôt qu'un nombre.

### `usePhotoSwipe`

Le hook est aujourd'hui muet : `usePhotoSwipe('#gallery', enabled)` ne renvoie
rien. Il doit exposer si la lightbox est ouverte, ce que la troisième condition
exige. C'est un retour booléen alimenté par les événements `beforeOpen` et
`close` que la lightbox émet déjà.

### L'état vide

Le texte devient celui de la maquette :

> Aucune photo pour le moment. Les clichés apparaissent ici quelques secondes
> après leur prise, gardez cette page ouverte.

La formulation actuelle — « rafraîchissez la page pour les découvrir » —
décrivait la limite qu'on lève ici. La promesse de la maquette devient vraie.

## Tests

### `tests/Feature/PartyPicturesStateTest.php`

- la forme de la réponse et son type ;
- `count` monte à la création d'une photo, baisse à la suppression ;
- `last_change` avance quand une vignette est écrite, sans que `count` bouge ;
- une galerie expirée renvoie des zéros sans requête ;
- la réponse est mémorisée : deux appels rapprochés ne font qu'une requête ;
- une photo créée, mise à jour ou supprimée invalide l'entrée, et l'appel
  suivant voit la nouvelle valeur — y compris via le `bulkDelete` du dashboard,
  qui est le chemin de suppression en masse existant ;
- le throttle `party-state` répond `429` au-delà du plafond.

### `tests/Web/GalleryAutoRefreshTest.php`

Cadence raccourcie par `config(['party.refresh.hot' => 1])`. Le cache n'a pas
besoin d'être désactivé : les écritures du test l'invalident.

- une photo insérée après le chargement apparaît toute seule, en haut de page ;
- la même photo, en mode sélection, ne touche pas à la grille et fait apparaître
  la pastille ; la sélection en cours est intacte ;
- un tap sur la pastille rafraîchit et la fait disparaître ;
- une galerie expirée n'émet aucune requête de sondage.

## Conséquences

Le budget serveur d'une soirée de six heures avec 80 invités connectés en
permanence : environ 170 000 requêtes HTTP sur `/api/parties/{party}/state`,
servies par le cache et sans écriture de session, et autant de requêtes SQL qu'il y a eu de photos dans la soirée —
quelques centaines. À comparer aux 240 requêtes/minute
que `party-picture` autorise déjà par soirée pour le seul partage.

Le sondage rend caduque une hypothèse de la refonte de la galerie
(`docs/superpowers/specs/2026-08-27-galerie-publique-refonte.md`) : la page
n'est plus un instantané figé au chargement. Les états vide et expiré, eux, ne
changent pas de forme — seul leur texte suit.
