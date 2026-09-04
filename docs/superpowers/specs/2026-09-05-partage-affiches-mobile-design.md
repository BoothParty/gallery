# Partage mobile des affiches — spécification

## Contexte

Les parties sont créées en lot depuis l'admin, à partir des évènements à venir remontés de
Notion. Chaque party a une affiche imprimable : le gabarit `resources/poster/template.png`
avec le QR code de la galerie et son adresse, généré à la volée par `PartyPosterGenerator`
et servi par `GET /admin/parties/{party}/poster`.

Ces affiches sont imprimées depuis un téléphone, avec l'application Canon Selphy. Aujourd'hui
il faut télécharger chaque affiche sur l'ordinateur puis la transférer à la main vers le
téléphone. L'objectif est de supprimer ce transfert manuel après une création en lot.

## Objectif

Depuis l'admin, obtenir en un geste toutes les affiches d'un lot de parties dans la
photothèque du téléphone — ou directement dans la feuille de partage qui alimente Selphy.

Critères de succès :

1. Après une création en lot, l'admin propose immédiatement un QR code menant aux affiches
   des parties créées.
2. Le même QR code est obtenable pour n'importe quelle sélection de parties dans le tableau.
3. Le téléphone qui scanne ce QR code partage toutes les affiches du lot sans authentification
   et sans installer quoi que ce soit.
4. Les navigateurs sans Web Share API de niveau 2 gardent un chemin de téléchargement.

## Hors périmètre

**LocalSend.** L'idée initiale était de pousser les affiches vers le téléphone via l'API REST
de LocalSend (protocole v2.2 : `POST /api/localsend/v2/prepare-upload` puis
`POST /api/localsend/v2/upload`). C'est écarté : l'application tourne sur un serveur distant
qui n'a aucune route vers le réseau local du téléphone, et l'appel ne peut pas non plus partir
du navigateur — `prepare-upload` renvoie un `sessionId` qu'il faut lire pour enchaîner, or
LocalSend n'expose pas d'en-têtes CORS, la page est en HTTPS face à un serveur local en
HTTP ou en certificat auto-signé, et Chrome restreint les requêtes d'un site public vers une
IP privée. Un relais tournant sur l'ordinateur (commande Artisan ou binaire dédié) reste
possible plus tard : il ne dépend d'aucun choix fait ici.

**Stockage des affiches.** Elles restent générées à la volée. Une génération coûte 0,86 s,
54 Mo de pic mémoire et produit 437 Ko de PNG : assez peu pour s'en passer.

**Vignettes.** La page téléphone n'affiche pas d'aperçu des affiches : prévisualiser
coûterait une génération complète par affiche alors que la page n'expose qu'un bouton global.

## Parcours

1. L'opérateur crée ses parties depuis « Create from events ».
2. L'admin revient sur la liste et ouvre une boîte de dialogue « Affiches » contenant le QR
   code du lot qui vient d'être créé.
3. Il scanne le QR code avec son téléphone, qui ouvre une page listant les parties du lot.
4. Il appuie sur « Tout partager » : la page prépare les affiches puis ouvre la feuille de
   partage native, d'où il enregistre dans la photothèque ou envoie vers Selphy.
5. Pour un lot plus ancien, il coche les parties voulues dans le tableau et déclenche la même
   boîte de dialogue depuis la barre d'outils.

## Architecture

### Lien signé

Un lot est décrit par la liste des identifiants de ses parties, portée par une URL signée
temporaire : `GET /affiches?parties=12,13,14&expires=…&signature=…`, valable 6 heures. La
signature couvre la liste, donc elle n'est pas manipulable.

`App\Services\PosterBatchLink` est seul responsable de cette construction : il valide les
identifiants, fabrique l'URL signée de la page, l'URL signée de chaque affiche — avec le
**même horodatage d'expiration**, pour qu'aucun fichier ne périme avant la page qui le
référence — et le QR code de la page en data-URI (`endroid/qr-code`, déjà installé).

### Routes

| Route | Nom | Accès | Rôle |
| --- | --- | --- | --- |
| `GET /admin/parties/posters-link?parties=12,13` | `admin.parties.posters-link` | session admin | Renvoie en JSON l'URL signée et le QR code d'une sélection |
| `GET /affiches` | `posters.index` | `signed` | Page Inertia listant les affiches du lot |
| `GET /affiches/{party:id}/png` | `posters.file` | `signed`, `throttle:30,1` | Streame le PNG de l'affiche |

La sélection voyage partout sous la même forme : des identifiants séparés par des virgules.
Les deux routes publiques sont déclarées avant le groupe `{party}` de `routes/web.php`, et
lient la party par son identifiant plutôt que par son nom, pour que l'URL ne dépende pas d'un
renommage entre la génération du lien et son ouverture.

### Back

| Fichier | Responsabilité |
| --- | --- |
| `app/Services/PosterBatchLink.php` (créé) | Construit les URLs signées et le QR code d'un lot |
| `app/Http/Controllers/Admin/PosterLinkController.php` (créé) | Expose le lien d'une sélection en JSON |
| `app/Http/Controllers/PosterController.php` (créé) | Sert la page publique et les PNG |
| `app/Http/Requests/PosterLinkRequest.php` (créé) | Valide la sélection reçue |
| `app/Http/Controllers/Admin/PartyController.php` (modifié) | `bulkStore` renvoie les identifiants créés en session |
| `routes/web.php` (modifié) | Déclare les trois routes |

`bulkStore` conserve sa transaction et redirige comme aujourd'hui, en ajoutant à la session
les identifiants des parties créées. La liste des parties les passe en prop, ce qui déclenche
l'ouverture de la boîte de dialogue au retour de la création. Le minage du lien passe ensuite
par la même route pour les deux chemins, création en lot et sélection manuelle.

`PosterController::file()` réutilise `PartyPosterGenerator` tel quel et répond en
`image/png` avec un nom de fichier lisible, comme le fait déjà l'action d'admin.

### Front

| Fichier | Responsabilité |
| --- | --- |
| `resources/js/components/parties/posters-dialog.tsx` (créé) | Boîte de dialogue QR code, récupère le lien et affiche les erreurs |
| `resources/js/pages/admin/parties/index.tsx` (modifié) | Ouvre la boîte après création, branche l'action de la barre d'outils |
| `resources/js/components/data-table/data-table-main.tsx` (modifié) | Remonte la sélection de lignes au parent |
| `resources/js/pages/party/posters.tsx` (créé) | Page téléphone : liste des parties, bouton de partage, repli |
| `resources/js/hooks/use-poster-share.ts` (créé) | Téléchargement des affiches, appel à `navigator.share`, états |

Le tableau gère déjà une sélection de lignes en interne mais ne l'expose pas ; on ajoute une
prop optionnelle qui la remonte, sans changer le comportement des autres tableaux.

La page téléphone s'appelle `party/posters` : `resources/views/app.blade.php` choisit le
bundle CSS public pour tout composant préfixé `party/`, et `resources/css/app.css` scanne
déjà `pages/party` et `hooks`. Aucune configuration de style n'est à toucher. La page ne
réutilise pas `party-layout`, qui attend une party unique.

`use-poster-share` s'inspire de `use-photo-share`, écrit sur la branche `feature/mobile-share`
mais absente d'ici : la logique est réécrite, pas importée.

## Détails de conception

**Partage.** Le bouton télécharge les affiches, au plus deux en parallèle pour ne pas empiler
les pics de 54 Mo côté serveur, construit un `File` par affiche, puis vérifie
`navigator.canShare({ files })` avant d'appeler `navigator.share`. La progression est affichée
pendant la préparation (« 3/5 affiches prêtes »).

**Repli.** Quand `navigator.canShare` manque ou refuse les fichiers — Firefox, navigateurs de
bureau — la page affiche à la place un lien de téléchargement par affiche. Pas d'archive ZIP :
elle demanderait une dépendance et du travail serveur pour un cas de repli.

**Annulation.** Un `navigator.share` annulé par l'utilisateur lève une `AbortError` : elle est
avalée sans message d'erreur, l'échec réel affiche un message.

**Expiration.** Six heures couvrent largement une session de préparation, et un lien qui fuite
ne survit pas à la journée. Une page ouverte après expiration répond 403 ; c'est le
comportement du middleware `signed`, sans traitement particulier.

## Sécurité

Le lien signé donne accès sans authentification aux affiches du lot pendant sa validité. Une
affiche ne contient que le QR code de la galerie publique et son adresse — jamais le
`host_token`, qui ouvre l'édition de la galerie. L'information exposée est donc celle qu'on
imprime et qu'on affiche au mur.

La route de fichier reçoit un limiteur de débit, pour qu'un lien partagé ne puisse pas servir
de générateur d'images à volonté : chaque appel consomme presque une seconde de CPU et 54 Mo.

## Tests

**Feature**

- `posters-link` : refuse un visiteur anonyme, valide la sélection, renvoie une URL signée qui
  passe la vérification de signature et un QR code non vide.
- `posters.index` : 403 sans signature et avec une signature expirée, 200 avec, et la page
  reçoit une entrée par party du lot.
- `posters.file` : 403 sans signature, 404 pour une party absente, 200 avec des octets PNG et
  les bons en-têtes, 429 au-delà du seuil.
- `bulkStore` : la session porte les identifiants des parties créées.

**Navigateur**

- Après une création en lot, la boîte de dialogue et son QR code apparaissent.
- Une sélection dans le tableau ouvre la même boîte.
- Sur la page téléphone, `navigator.share` bouchonné reçoit autant de fichiers que de parties.
- Sans Web Share, la page affiche les liens de téléchargement.

**Unit**

- `PosterBatchLink` : mêmes expirations pour la page et les fichiers, identifiants inconnus
  rejetés.

## Risques et limites

- **Poids du partage.** Cinq affiches font environ 2 Mo au total ; un lot de vingt approche
  9 Mo, ce qui reste raisonnable pour la feuille de partage, mais n'a pas été éprouvé sur iOS.
  Si la feuille native cale, la porte de sortie est d'ajouter un partage affiche par affiche ;
  ce n'est pas implémenté ici.
- **Coût serveur.** Un lot de vingt affiches représente une vingtaine de secondes de CPU. La
  limite de deux téléchargements simultanés borne le pic mémoire à une centaine de mégaoctets.
- **Selphy.** Que l'application Canon apparaisse dans la feuille de partage n'est pas garanti ;
  l'enregistrement dans la photothèque, lui, l'est, et c'est le parcours actuel.
