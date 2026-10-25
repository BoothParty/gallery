# Réimpression de photos depuis la galerie

Date : 2026-10-25

## Contexte

Pendant la soirée, la borne est sur place et connectée. L'hôte parcourt la
galerie sur son téléphone et veut faire ressortir certaines photos sur papier :
aujourd'hui, il doit aller trouver la borne. Ce qu'on veut, c'est qu'il
sélectionne les photos dans la galerie, appuie sur « Réimprimer », et que la
borne les imprime à la suite.

Cette spec couvre la galerie, la base, le back-office et l'API que la borne
interrogera. Le plugin pibooth qui consomme cette API est un chantier séparé,
traité plus tard.

## Objectif

- L'hôte sélectionne des photos et lance une réimpression en un geste, avec un
  toast de confirmation.
- Chaque photo sélectionnée devient un job d'impression en attente ; la borne
  récupère les jobs en attente et les marque imprimés.
- Une photo qui a au moins un job en attente porte un badge dans la galerie de
  l'hôte et dans la galerie admin.
- Le back-office filtre les photos en attente d'impression, et le fil
  d'activité du dashboard trace les demandes et les impressions.

## Périmètre

Dans le périmètre :

- une table `print_jobs` et son modèle ;
- l'endpoint web de demande, réservé à l'hôte ;
- le bouton secondaire, le toast et le badge dans la galerie publique ;
- le filtre et le badge dans la galerie admin ;
- deux nouveaux types d'activité ;
- deux endpoints d'API pour la borne.

Hors périmètre (non-objectifs) :

- le plugin pibooth ;
- la réimpression par les invités, ou depuis le back-office ;
- l'annulation d'un job, un statut « échec », l'expiration des jobs : un job
  que la borne ne confirme jamais reste en attente pour toujours, c'est voulu ;
- une confirmation ou un plafond dédié pour les grosses sélections : un tap
  sur « Réimprimer » avec 300 photos sélectionnées part directement (décision
  de l'utilisateur) ;
- un choix de quantité : une photo sélectionnée = un job = une copie.
  Redemander la même photo crée un job de plus ;
- la disparition du badge en direct quand la borne imprime : il disparaît au
  prochain chargement de la page ;
- la coordination entre plusieurs bornes sur une même party : une seule borne
  par party, pas de verrou de prise en charge ;
- un historique des impressions passées dans l'interface.

Anti-objectifs (ce qui ferait échouer la feature même si elle « marche ») :

- un invité, ou une autre party, qui parvient à créer des jobs : chaque job
  coûte du papier pendant la soirée ;
- une demande qui part deux fois au double-tap et imprime tout en double sans
  que l'hôte l'ait voulu.

## Modèle de données

### Table `print_jobs`

| Colonne      | Type                                              |
|--------------|---------------------------------------------------|
| `id`         | id                                                |
| `picture_id` | `foreignId` → `pictures`, `cascadeOnDelete()`     |
| `printed_at` | `timestamp` nullable                              |
| timestamps   |                                                   |

Index `(picture_id, printed_at)`.

Le statut tient dans `printed_at` : `null` = en attente, rempli = imprimé.
C'est le même principe que `pictures.hidden_at`, sans enum. Pas de `party_id` :
la party se retrouve par la photo (`pictures.party_id` est indexé).

### Modèle `PrintJob`

- `picture(): BelongsTo`, scope `pending()` (`whereNull('printed_at')`),
  factory avec un état `printed()`.
- `Picture::printJobs(): HasMany`.
- `Party::printJobs(): HasManyThrough` (via `pictures`), utilisé par l'API et
  pour la liaison de route scopée.

### Types d'activité

Deux cas ajoutés à `PartyActivityType`, repliés sur 15 minutes comme les autres
(comportement par défaut de `foldWithin()`), listés dans le fil :

| Cas                     | Valeur                     | Description                                   |
|-------------------------|----------------------------|-----------------------------------------------|
| `PrintsRequested`       | `prints_requested`         | « 3 photos envoyées à l'impression »          |
| `PicturesPrinted`       | `pictures_printed`         | « 3 photos réimprimées »                      |

Les accords suivent le modèle existant (`photo`/`photos`, `envoyée`/`envoyées`,
`réimprimée`/`réimprimées`).

## Demande de réimpression (hôte)

Route web `POST {party}/prints`, nom `party.prints.store`, middlewares
`party.host` et `throttle:party-metadata` comme `pictures.visibility`.

Form request calquée sur `DownloadPicturesRequest` :

- `authorize()` refuse une party expirée (`hidePictures()`) ;
- `ids` requis, tableau, `min:1`, `max:config('party.download.max_pictures')` ;
- `ids.*` entier, `distinct`, `PictureBelongsToParty($party, true)` : l'hôte
  peut réimprimer une photo masquée, puisqu'il la voit.

Le contrôleur crée un job par id (une insertion groupée), enregistre
`PrintsRequested` avec `count` = nombre de jobs, puis `back()`.

Une party de type *transfer* n'a pas de bouton : son `host_token` n'est jamais
exposé, donc `canEdit` y est toujours faux. Aucun cas particulier côté serveur.

## Galerie publique (hôte)

### Données

`PartyController::pictures()` ajoute, pour l'hôte seulement,
`withExists(['printJobs as print_pending' => fn ($q) => $q->pending()])`.
`PictureResource` expose `print_pending` (booléen) lorsqu'il est chargé, et
rien sinon : l'API existante de la borne et les invités ne reçoivent pas le
champ.

### Barre de sélection

`selection-bar.tsx` reçoit `canReprint` et `onReprint`. Quand `canReprint`
(= `canEdit`), un bouton secondaire « Réimprimer » apparaît à gauche de
« Télécharger » : contour `cream/45`, texte cream, icône imprimante.
Sous `sm`, il se réduit à l'icône, avec `aria-label="Réimprimer"`.

Le bouton est `type="button"` hors du flux du formulaire ZIP. Pendant l'envoi,
il est désactivé, ce qui couvre le double-tap.

### Après le clic

- Envoi par `router.post` d'Inertia (`preserveScroll`, `preserveState`,
  `only: ['picturesState']`), sans recharger la liste paginée.
- Succès : toast « 3 photos envoyées à l'impression » (« 1 photo envoyée à
  l'impression »), via le `flash` existant de `gallery.tsx` ; le mode sélection
  se ferme (`selection.exit()`) ; les ids envoyés passent en « en attente »
  localement, pour que leurs badges apparaissent sans rechargement.
- Échec (validation, throttle, réseau) : toast « La réimpression n'a pas pu
  être lancée », la sélection est conservée.

### Badge

`photo-tile.tsx` reçoit `printPending`. S'il est vrai, une pastille
non cliquable (26 px, fond `ink/35`, bordure pointillée `cream/85`, icône
imprimante 13 px) s'affiche en haut à gauche :

- à `left: 40px`, collée au bouton œil, quand celui-ci est affiché
  (`canEdit && !selectMode`) ;
- à `left: 8px` sinon (mode sélection).

`title` et `aria-label` : « Impression en attente ». Les invités ne voient
jamais le badge, puisqu'ils ne reçoivent pas `print_pending`.

## Back-office

Dans `PartyDashboardController::pictures()` :

- paramètre `?print=pending` : restreint aux photos qui ont un job en attente
  (`whereHas('printJobs', fn ($q) => $q->pending())`) ; toute autre valeur est
  ignorée ;
- chaque photo porte `printPending` (même `withExists` que la galerie) ;
- la page reçoit le filtre courant, et la pagination le conserve
  (`withQueryString()`).

Dans `admin/gallery/index.tsx` : un toggle « Impression en attente » au-dessus
de la grille, et le même badge sur les vignettes concernées.

Le fil d'activité du dashboard affiche les deux nouveaux types sans autre
changement, puisqu'il liste tout type où `isListed()` est vrai.

## API borne

Dans le groupe `auth:sanctum` existant de `routes/api.php`, même token que
pour l'envoi des photos :

### `GET api/parties/{party}/print-jobs`

Jobs en attente de la party, du plus ancien au plus récent, sans pagination.
`PrintJobResource` :

```json
{
  "data": [
    {
      "id": 12,
      "picture_id": 345,
      "url": "<URL signée de l'original>",
      "created_at": "2026-10-25T22:14:03+02:00"
    }
  ]
}
```

### `POST api/parties/{party}/print-jobs/{printJob}/printed`

Liaison scopée à la party : un job d'une autre party renvoie 404. Remplit
`printed_at` s'il est vide et enregistre `PicturesPrinted` (`count: 1`) ; un
second appel ne change rien et n'ajoute pas d'activité. Renvoie le
`PrintJobResource`.

La borne interroge la liste à intervalle régulier ; ce rythme appartient au
plugin pibooth.

## Tests

- Feature : demande hôte (création, activité, refus invité, refus id d'une
  autre party, refus party expirée, photo masquée acceptée) ; galerie
  (`print_pending` pour l'hôte, absent pour l'invité) ; back-office (filtre) ;
  API (liste en attente seulement, ordre, scoping, idempotence, auth requise).
- Navigateur (`tests/Web/`) : hôte qui sélectionne, réimprime, voit le toast
  et le badge ; invité qui ne voit pas le bouton.

## Découpage en lots

Une épique « Réimpression de photos », quatre lots. Le lot 1 bloque les trois
autres, qui sont parallélisables entre eux.

1. **Back-end** : migration `print_jobs`, modèle, factory, relations,
   types d'activité, route `POST {party}/prints` avec sa form request.
2. **Galerie (hôte)** : `print_pending` dans la galerie, bouton secondaire,
   toast, badge, tests navigateur.
3. **Back-office** : filtre `?print=pending`, badge admin.
4. **API borne** : les deux endpoints, `PrintJobResource`.

## Décisions prises en cours de route (à relire)

Les choix suivants sont les miens, pas ceux de l'utilisateur :

- `print_jobs` sans `party_id` ; statut porté par `printed_at` ;
- plafond de la demande aligné sur celui du ZIP (300) ;
- limiteur `party-metadata` réutilisé ;
- refus d'une demande sur une party expirée ;
- fermeture du mode sélection après une réimpression réussie (aujourd'hui, le
  téléchargement ne vide rien) ;
- photo masquée réimprimable ;
- bouton réduit à l'icône sur mobile ;
- badges ajoutés localement après succès plutôt que rechargement ;
- badge aussi dans la galerie admin ;
- activité `PicturesPrinted` en plus de la demande ;
- nommage `print_jobs` / `prints` / `print-jobs`.
