# Réimpression de photos — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** L'hôte sélectionne des photos dans la galerie et les envoie à la borne pour réimpression ; la borne récupère les jobs en attente par API et les marque imprimés.

**Architecture:** Une table `print_jobs` (statut porté par `printed_at`) rattachée aux photos. Une route web hôte crée les jobs ; la galerie publique et la galerie admin lisent un booléen `print_pending` calculé par `withExists` ; deux endpoints Sanctum servent la borne. Le fil d'activité reçoit deux nouveaux types.

**Tech Stack:** Laravel 13, Inertia v2, React 19, Tailwind v4, Pest 4 (+ plugin navigateur), Sail.

**Spec:** `docs/superpowers/specs/2026-10-25-reimpression-photos.md`

## Global Constraints

- Toutes les commandes passent par `vendor/bin/sail` ; `vendor/bin/sail bin pint --dirty --format agent` avant chaque commit touchant du PHP.
- Créer les fichiers avec `vendor/bin/sail artisan make:* --no-interaction` (migration, model `-f`, request, resource, controller, test `--pest`).
- Nommage figé : table `print_jobs`, modèle `App\Models\PrintJob`, route web `party.prints.store` (`POST {party}/prints`), routes API `print-jobs.index` / `print-jobs.printed`.
- Statut : `printed_at` nul = en attente. Pas d'enum, pas de `party_id` sur `print_jobs`.
- Plafond d'une demande : `config('party.download.max_pictures')` (300). Aucune confirmation côté client.
- Textes galerie publique (français, exacts) : bouton « Réimprimer » ; toasts « 1 photo envoyée à l'impression » / « N photos envoyées à l'impression » / « La réimpression n'a pas pu être lancée » ; badge `title`/`aria-label` « Impression en attente ».
- Textes fil d'activité : « N photo(s) envoyée(s) à l'impression », « N photo(s) réimprimée(s) » (mêmes règles d'accord que `PartyActivityType::description()`).
- Back-office en anglais comme le reste de l'admin : toggle « Pending prints », badge `title` « Print pending ».
- Composants publics dans `resources/js/components/party/` (bundle `app.css` sans shadcn).
- Ne rien pousser ni ouvrir de PR sans demande explicite.

## Review Focus

1. Une photo supprimée alors qu'elle a un job en attente disparaît de la liste de la borne (cascade) — test Task 1.
2. Une photo masquée avec un job en attente reste dans la liste de la borne — test Task 6.
3. Une photo dont tous les jobs sont imprimés n'a plus de badge et sort du filtre, alors qu'un job imprimé + un en attente la garde — tests Tasks 3 et 5.
4. Une sélection au-delà du plafond affiche un toast de limite sans envoyer de requête — test navigateur Task 4.
5. Changer le nombre par page dans la galerie admin filtrée garde le filtre — test navigateur Task 5.

---

### Task 1: Table, modèle et types d'activité (lot 1)

**Files:**
- Create: `database/migrations/2026_10_25_100000_create_print_jobs_table.php`, `app/Models/PrintJob.php`, `database/factories/PrintJobFactory.php`
- Modify: `app/Models/Picture.php`, `app/Models/Party.php`, `app/Enums/PartyActivityType.php`
- Test: `tests/Feature/PrintJobTest.php`, `tests/Feature/PartyActivityTest.php`

**Interfaces:**
- Produces: `PrintJob` (`$fillable = ['printed_at']`, cast `printed_at` datetime), `PrintJob::picture(): BelongsTo`, scope `PrintJob::pending()`, `PrintJobFactory::printed(): static` ; `Picture::printJobs(): HasMany` ; `Party::printJobs(): HasManyThrough` (via `Picture`) ; `PartyActivityType::PrintsRequested` (`prints_requested`), `PartyActivityType::PicturesPrinted` (`pictures_printed`).

- [ ] **Step 1: Write the failing tests** dans `tests/Feature/PrintJobTest.php` :

```php
it('keeps only unprinted jobs in the pending scope', function () {
    $picture = Picture::factory()->create();
    $pending = PrintJob::factory()->for($picture)->create();
    PrintJob::factory()->for($picture)->printed()->create();

    expect(PrintJob::pending()->pluck('id')->all())->toBe([$pending->id]);
});

it('reaches its jobs from the party through the pictures', function () {
    $party = Party::factory()->create();
    $job = PrintJob::factory()->for(Picture::factory()->for($party))->create();
    PrintJob::factory()->create();

    expect($party->printJobs()->pluck('print_jobs.id')->all())->toBe([$job->id]);
});

it('drops the jobs of a deleted picture', function () {
    Storage::fake('s3');
    $job = PrintJob::factory()->create();

    $job->picture->delete();

    $this->assertModelMissing($job);
});
```

Et dans `tests/Feature/PartyActivityTest.php`, un dataset sur `description()` : `PrintsRequested` + `count 1` → `1 photo envoyée à l'impression`, `count 3` → `3 photos envoyées à l'impression` ; `PicturesPrinted` + `1` → `1 photo réimprimée`, `3` → `3 photos réimprimées` ; les deux `isListed()` vrais et `foldWithin()` = 15 minutes.

- [ ] **Step 2: Run** `vendor/bin/sail artisan test --compact --filter='PrintJobTest|PartyActivityTest'` — Expected: FAIL (classe `PrintJob` introuvable).

- [ ] **Step 3: Implement** la migration (colonnes de la spec, `foreignId('picture_id')->constrained()->cascadeOnDelete()`, `timestamp('printed_at')->nullable()`, index `['picture_id', 'printed_at']`), le modèle, la factory (`picture_id => Picture::factory()`, `printed()` remplit `printed_at`), les relations et les deux cas d'enum (`description()` suit le `match` existant).

- [ ] **Step 4: Run** la même commande — Expected: PASS.

- [ ] **Step 5: Commit** `feat(prints): table print_jobs et types d'activité`.

### Task 2: Demande de réimpression par l'hôte (lot 1)

**Files:**
- Create: `app/Http/Controllers/PictureReprintController.php` (invokable), `app/Http/Requests/ReprintPicturesRequest.php`
- Modify: `routes/web.php` (dans le groupe `{party}`, à côté de `pictures.visibility`)
- Test: `tests/Feature/PictureReprintTest.php`

**Interfaces:**
- Consumes: Task 1.
- Produces: `POST /{party}/prints`, nom `party.prints.store`, middlewares `['party.host', 'throttle:party-metadata']`, corps `ids[]`, réponse `back()`.

- [ ] **Step 1: Write the failing tests** (réutiliser le helper `hostSession()` de `PictureVisibilityTest.php` en le déplaçant dans `tests/Pest.php`, sans changer sa signature) :
  - `creates one pending job per selected picture` : 3 photos, POST des 3 ids → redirect, `PrintJob::pending()->count() === 3`, chaque `picture_id` présent.
  - `queues a second copy when a picture is asked again` : un job en attente existant + nouvelle demande → 2 jobs pour la photo.
  - `records the request in the activity feed` : 3 ids → une activité `PrintsRequested` avec `properties['count'] === 3`.
  - `lets the host reprint a hidden picture` : photo `hidden()` → 1 job.
  - `refuses a plain visitor` : sans session hôte → 403, 0 job.
  - `refuses a picture from another party` : `assertSessionHasErrors('ids.0')`, 0 job.
  - `refuses the same picture twice in one request` : `assertSessionHasErrors('ids.1')`.
  - `refuses more pictures than the cap` : `config(['party.download.max_pictures' => 1])`, 2 ids → `assertSessionHasErrors('ids')`.
  - `refuses an expired party` : `expire_at` passé → 403.

- [ ] **Step 2: Run** `vendor/bin/sail artisan test --compact tests/Feature/PictureReprintTest.php` — Expected: FAIL (route introuvable).

- [ ] **Step 3: Implement.** `ReprintPicturesRequest` reprend `DownloadPicturesRequest` (`authorize` = `! hidePictures()`, mêmes règles, `PictureBelongsToParty($party, true)`, messages adaptés « …reprint… »). Le contrôleur fait un `PrintJob::insert()` groupé (avec timestamps), puis `recordActivity(PrintsRequested, ['count' => count($ids)])`, puis `back()`.

- [ ] **Step 4: Run** la même commande, puis `--filter=PictureVisibilityTest` (helper déplacé) — Expected: PASS.

- [ ] **Step 5: Commit** `feat(prints): demande de réimpression par l'hôte`.

### Task 3: Badge « Impression en attente » dans la galerie (lot 2)

**Files:**
- Modify: `app/Http/Controllers/PartyController.php` (`pictures()`), `app/Http/Resources/PictureResource.php`, `resources/js/pages/party/gallery.tsx` (type `Picture`), `resources/js/components/party/gallery-grid.tsx`, `resources/js/components/party/photo-tile.tsx`
- Test: `tests/Feature/GalleryTest.php`

**Interfaces:**
- Consumes: `Picture::printJobs()`, scope `pending()`.
- Produces: champ `print_pending: boolean` dans `PictureResource` (présent seulement si chargé, via `$this->whenHas('print_pending', …)` casté en bool) ; type TS `print_pending?: boolean` ; `GalleryGrid` prop `isPrintPending?: (picture: Picture) => boolean` (défaut `picture.print_pending === true`) ; `PhotoTile` prop `printPending?: boolean`, élément `data-print-pending`.

- [ ] **Step 1: Write the failing tests** dans `GalleryTest.php` :
  - `tells the host which pictures wait for a print` : photo A avec job en attente, photo B avec seulement un job imprimé, photo C avec un imprimé + un en attente → en session hôte, `pictures.data` porte `print_pending` vrai pour A et C, faux pour B.
  - `never sends print state to a guest` : même jeu sans session → `print_pending` absent de chaque item (`->missing('pictures.data.0.print_pending')`).

- [ ] **Step 2: Run** `vendor/bin/sail artisan test --compact tests/Feature/GalleryTest.php` — Expected: FAIL.

- [ ] **Step 3: Implement.** `PartyController::pictures()` : `->when($isHost, fn ($q) => $q->withExists(['printJobs as print_pending' => fn ($jobs) => $jobs->pending()]))`. Badge dans `PhotoTile` selon la spec (pastille 26 px non cliquable, fond `ink/35`, bordure pointillée `cream/85`, icône imprimante 13 px dessinée en SVG inline comme `EyeIcon`, `left-10` quand le bouton œil est affiché (`canEdit && !selectMode`), `left-2` sinon, `top-2`). `GalleryGrid` transmet `printPending={isPrintPending(picture)}`.

- [ ] **Step 4: Run** la même commande — Expected: PASS. `vendor/bin/sail npm run build` sans erreur.

- [ ] **Step 5: Commit** `feat(prints): badge impression en attente dans la galerie de l'hôte`.

### Task 4: Bouton « Réimprimer » et toast (lot 2)

**Files:**
- Modify: `resources/js/components/party/selection-bar.tsx`, `resources/js/pages/party/gallery.tsx`
- Test: `tests/Web/GalleryReprintTest.php` (create)

**Interfaces:**
- Consumes: route `party.prints.store` (Task 2), `isPrintPending` (Task 3).
- Produces: `SelectionBar` props `canReprint: boolean`, `reprinting: boolean`, `onReprint: () => void` ; bouton `data-reprint-button`, `type="button"`, désactivé si `count === 0 || reprinting`.

- [ ] **Step 1: Write the failing browser tests** (modèle : `tests/Web/GalleryMaskingTest.php`, `browserPage()`) :
  - `shows no reprint button to a guest` : `assertMissing('[data-reprint-button]')` après entrée en mode sélection.
  - `reprints the selection and marks the tiles` : hôte, 2 photos, entrer en sélection, cliquer les 2 tuiles, cliquer `[data-reprint-button]` → `assertSee('2 photos envoyées à l'impression')`, `assertCount('[data-print-pending]', 2)`, mode sélection fermé ; `PrintJob::pending()->count() === 2`.
  - `refuses a selection over the cap without sending it` : `config(['party.download.max_pictures' => 1])`, 2 photos sélectionnées → `assertSee('Sélection trop grande : 1 photos maximum par réimpression')`, `PrintJob::count() === 0`.

- [ ] **Step 2: Run** `vendor/bin/sail artisan test --compact tests/Web/GalleryReprintTest.php` — Expected: FAIL.

- [ ] **Step 3: Implement.**
  - `SelectionBar` : bouton secondaire à gauche du bouton de téléchargement (contour `cream/45`, texte cream, icône imprimante ; libellé masqué sous `sm`, `aria-label="Réimprimer"`), rendu seulement si `canReprint`.
  - `gallery.tsx` : état local `printRequests: Set<number>` + `reprinting: boolean` ; `isPrintPending = (p) => printRequests.has(p.id) || p.print_pending === true`. `onReprint` : si `selection.count > maxDownloadPictures` → `flash('Sélection trop grande : ${max} photos maximum par réimpression')` et sortir ; sinon `router.post('/${encodeURIComponent(party.name)}/prints', { ids: selection.selectedIds }, { preserveScroll: true, preserveState: true, only: ['picturesState'], onStart/onFinish → reprinting, onSuccess → ajouter les ids à printRequests, flash(texte exact), selection.exit(), onError → flash('La réimpression n'a pas pu être lancée') })`. `canReprint={canEdit}`.

- [ ] **Step 4: Run** la même commande, puis `tests/Web/GalleryTest.php` et `tests/Web/GalleryMaskingTest.php` (non-régression de la barre) — Expected: PASS. Si les tests tournent contre un `public/hot` présent, voir la mémoire « Vite périmé dans les tests navigateur ».

- [ ] **Step 5: Commit** `feat(prints): bouton réimprimer dans la barre de sélection`.

### Task 5: Filtre et badge dans la galerie admin (lot 3)

**Files:**
- Modify: `app/Http/Controllers/PartyDashboardController.php` (`pictures()`), `resources/js/pages/admin/gallery/index.tsx`, `resources/js/components/gallery/gallery-grid.tsx`, `resources/js/components/gallery/gallery-pagination.tsx`
- Test: `tests/Feature/AdminGalleryTest.php`, `tests/Web/AdminGalleryTest.php`

**Interfaces:**
- Consumes: `Picture::printJobs()`, scope `pending()`.
- Produces: query `?print=pending` ; prop page `pendingPrintsOnly: boolean` ; champ `printPending: boolean` sur chaque `GalleryPicture` ; `GalleryPagination` prop `pendingPrintsOnly: boolean` ajoutée à `data` (`print: 'pending'`) quand vraie ; toggle `data-pending-prints-filter` (`aria-pressed`) ; badge `data-print-pending`.

- [ ] **Step 1: Write the failing tests.**
  - Feature `filters the pictures waiting for a print` : A (en attente), B (imprimé seulement), C (sans job) → `?print=pending` : `pictures.total === 1`, id de A, `pendingPrintsOnly === true`, `pictures.data.0.printPending === true`.
  - Feature `ignores an unknown print filter` : `?print=foo` → total 3, `pendingPrintsOnly === false`.
  - Web `keeps the print filter when changing the page size` : visiter avec `?print=pending`, changer le nombre par page → l'URL contient `print=pending` et le badge `[data-print-pending]` reste affiché.

- [ ] **Step 2: Run** `vendor/bin/sail artisan test --compact --filter=AdminGalleryTest` — Expected: FAIL.

- [ ] **Step 3: Implement.** Contrôleur : `$pendingPrintsOnly = $request->query('print') === 'pending'`, `withExists` comme Task 3 (`as print_pending`), `->when($pendingPrintsOnly, fn ($q) => $q->whereHas('printJobs', fn ($jobs) => $jobs->pending()))`, `printPending` dans le `through()`. Page : bouton `Toggle`/`Button variant="outline"` « Pending prints » dans `toolbarAction` à côté de « Upload pictures », qui visite la même route avec `per_page` et, si activé, `print: 'pending'`. Badge `PrinterIcon` (lucide) en haut à gauche des vignettes, `title="Print pending"`.

- [ ] **Step 4: Run** la même commande — Expected: PASS.

- [ ] **Step 5: Commit** `feat(prints): filtre impressions en attente dans la galerie admin`.

### Task 6: API de la borne (lot 4)

**Files:**
- Create: `app/Http/Controllers/Api/PrintJobController.php`, `app/Http/Resources/PrintJobResource.php`
- Modify: `routes/api.php` (dans le groupe `auth:sanctum`)
- Test: `tests/Feature/Api/PrintJobApiTest.php`

**Interfaces:**
- Consumes: `Party::printJobs()`, scope `pending()`, `PartyActivityType::PicturesPrinted`.
- Produces:
  - `GET api/parties/{party}/print-jobs` → `PrintJobController@index`, nom `print-jobs.index`.
  - `POST api/parties/{party}/print-jobs/{printJob}/printed` → `PrintJobController@printed`, nom `print-jobs.printed`, `->scopeBindings()`.
  - `PrintJobResource` : `id`, `picture_id`, `url` (`$this->picture->url()`), `created_at` ISO 8601.

- [ ] **Step 1: Write the failing tests** (modèle : `tests/Feature/Api/PictureApiTest.php`, `Sanctum::actingAs`) :
  - `rejects unauthenticated requests` sur les deux routes → 401.
  - `lists the pending jobs oldest first` : 2 jobs en attente créés dans l'ordre + 1 imprimé → `data` = les 2 en attente dans l'ordre de création, avec `picture_id` et `url`.
  - `lists the jobs of hidden pictures` : photo `hidden()` avec job en attente → listée.
  - `leaves out other parties jobs` : job d'une autre party absent.
  - `marks a job printed and logs it` : POST → 200, `printed_at` rempli, une activité `PicturesPrinted` `count 1`.
  - `ignores a second confirmation` : deux POST → `printed_at` inchangé après le second, toujours une seule unité comptée.
  - `refuses a job of another party` : → 404.

- [ ] **Step 2: Run** `vendor/bin/sail artisan test --compact tests/Feature/Api/PrintJobApiTest.php` — Expected: FAIL.

- [ ] **Step 3: Implement.** `index` : `$party->printJobs()->pending()->with('picture')->oldest('print_jobs.id')->get()` dans `PrintJobResource::collection`. `printed` : si `printed_at` est nul, le remplir et enregistrer l'activité ; renvoyer la ressource.

- [ ] **Step 4: Run** la même commande — Expected: PASS.

- [ ] **Step 5: Commit** `feat(prints): API de la borne pour les jobs d'impression`.

### Finition

- [ ] `vendor/bin/sail artisan test --compact` sur `tests/Feature` complet, puis `tests/Web` — tout vert.
- [ ] `vendor/bin/sail bin pint --dirty --format agent` et `vendor/bin/sail npm run build` sans erreur.
