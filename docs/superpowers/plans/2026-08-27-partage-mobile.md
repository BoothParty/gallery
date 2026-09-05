# Partage mobile de la sélection — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sur les navigateurs qui supportent la Web Share API de niveau 2, le bouton « Télécharger » de la barre de sélection télécharge les photos choisies puis ouvre la feuille de partage native ; partout ailleurs il reste le ZIP actuel.

**Architecture:** Une route publique same-origin sert l'original d'une photo depuis S3, ce qui contourne l'impossibilité de lire des octets cross-origin en JavaScript. Un hook `use-photo-share` détient le téléchargement, l'appel à `navigator.share` et le traitement des erreurs ; `selection-bar.tsx` ne fait qu'afficher l'état qu'on lui passe.

**Tech Stack:** Laravel 12, Inertia v2, React 19, Tailwind CSS 4, Pest 4 (tests navigateur Playwright).

**Spec :** `docs/superpowers/specs/2026-08-27-partage-mobile.md`

## Global Constraints

- Toutes les commandes passent par Sail : `vendor/bin/sail artisan …`, `vendor/bin/sail npm …`, `vendor/bin/sail bin pint …`.
- Les composants et hooks de la galerie publique vivent sous `resources/js/components/party/` ou `resources/js/hooks/` : `resources/css/app.css` ne scanne que `app.tsx`, `pages/party`, `components/party`, `layouts/party-layout.tsx` et `hooks`. Ailleurs, ils perdent tous leurs styles.
- PHP : accolades systématiques, types de retour explicites, promotion de propriétés dans les constructeurs, PHPDoc plutôt que commentaires en ligne.
- Les messages de validation et les commentaires de code sont en anglais ; les textes affichés à l'invité sont en français.
- Après toute modification PHP : `vendor/bin/sail bin pint --dirty --format agent`.
- Après toute modification front, reconstruire avant de lancer les tests navigateur : `vendor/bin/sail npm run build`.
- Les tests de `tests/Web/` flottent sur des délais de 5 s : les relancer une fois avant d'enquêter.
- Aucune nouvelle clé de configuration : le plafond du partage est `party.download.max_pictures`, déjà exposé à la page en prop `maxDownloadPictures`.

## File Structure

| Fichier | Responsabilité |
| --- | --- |
| `app/Http/Controllers/PictureFileController.php` (créé) | Streame l'original d'une photo depuis S3, sous l'origine de l'application |
| `routes/web.php` (modifié) | Déclare `party.pictures.show`, avec `scopeBindings()` et le limiteur |
| `app/Providers/AppServiceProvider.php` (modifié) | Déclare le limiteur `party-picture` |
| `tests/Feature/PictureFileTest.php` (créé) | Couvre la route : octets, en-têtes, cloisonnement, expiration, limitation |
| `resources/js/hooks/use-photo-share.ts` (créé) | Détection du support, téléchargement, appel à `navigator.share`, erreurs |
| `resources/js/components/party/selection-bar.tsx` (modifié) | Affiche le bouton et sa progression selon le mode reçu en props |
| `resources/js/pages/party/gallery.tsx` (modifié) | Choisit le mode, branche le hook sur le toast existant |
| `tests/Web/GalleryTest.php` (modifié) | Couvre les deux chemins du bouton au navigateur |

---

### Task 1 : Route de fichier same-origin

**Files:**
- Create: `app/Http/Controllers/PictureFileController.php`
- Create: `tests/Feature/PictureFileTest.php`
- Modify: `routes/web.php`
- Modify: `app/Providers/AppServiceProvider.php`

**Interfaces:**
- Consumes: `App\Models\Party::hidePictures()`, `App\Models\Picture::$path`, le disque `s3`.
- Produces: la route nommée `party.pictures.show`, d'URL `/{party}/pictures/{picture}`, qui répond 200 avec les octets JPEG de la photo, 404 si la photo n'appartient pas à la party ou si le fichier manque, 403 si la galerie est expirée, 429 au-delà de 240 requêtes par minute et par couple IP + party.

- [x] **Step 1: Écrire les tests de la route**

Créer `tests/Feature/PictureFileTest.php` :

```php
<?php

use App\Models\Party;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

it('streams the original bytes of a picture', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();
    Storage::disk('s3')->put($picture->path, 'fake-jpeg-bytes');

    $response = $this->get(route('party.pictures.show', [$party, $picture]));

    $response->assertOk()
        ->assertHeader('content-type', 'image/jpeg');

    expect($response->streamedContent())->toBe('fake-jpeg-bytes');
});

it('lets browsers cache a picture forever', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();
    Storage::disk('s3')->put($picture->path, 'fake-jpeg-bytes');

    $this->get(route('party.pictures.show', [$party, $picture]))
        ->assertOk()
        ->assertHeader('cache-control', config('filesystems.disks.s3.options.CacheControl'));
});

it('does not serve a picture belonging to another party', function () {
    $party = Party::factory()->create();
    $otherParty = Party::factory()->create();
    $foreignPicture = Picture::factory()->for($otherParty)->create();
    Storage::disk('s3')->put($foreignPicture->path, 'fake-jpeg-bytes');

    $this->get(route('party.pictures.show', [$party, $foreignPicture]))
        ->assertNotFound();
});

it('does not serve a picture whose file is missing from storage', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();

    $this->get(route('party.pictures.show', [$party, $picture]))
        ->assertNotFound();
});

it('refuses to serve a picture of an expired party', function () {
    $party = Party::factory()->expired()->create();
    $picture = Picture::factory()->for($party)->create();
    Storage::disk('s3')->put($picture->path, 'fake-jpeg-bytes');

    $this->get(route('party.pictures.show', [$party, $picture]))
        ->assertForbidden();
});

it('throttles repeated picture requests', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();
    Storage::disk('s3')->put($picture->path, 'fake-jpeg-bytes');

    foreach (range(1, 240) as $ignored) {
        $this->get(route('party.pictures.show', [$party, $picture]))->assertOk();
    }

    $this->get(route('party.pictures.show', [$party, $picture]))
        ->assertStatus(429);
});

it('keeps picture throttling independent per party', function () {
    $throttledParty = Party::factory()->create();
    $throttledPicture = Picture::factory()->for($throttledParty)->create();
    Storage::disk('s3')->put($throttledPicture->path, 'fake-jpeg-bytes');

    foreach (range(1, 240) as $ignored) {
        $this->get(route('party.pictures.show', [$throttledParty, $throttledPicture]))->assertOk();
    }

    $this->get(route('party.pictures.show', [$throttledParty, $throttledPicture]))
        ->assertStatus(429);

    $otherParty = Party::factory()->create();
    $otherPicture = Picture::factory()->for($otherParty)->create();
    Storage::disk('s3')->put($otherPicture->path, 'fake-jpeg-bytes');

    $this->get(route('party.pictures.show', [$otherParty, $otherPicture]))
        ->assertOk();
});
```

Note : `phpunit.xml` fixe `CACHE_STORE=array`, donc le compteur du limiteur repart de zéro à chaque test ; les deux tests de limitation ne se polluent pas.

- [x] **Step 2: Lancer les tests pour les voir échouer**

Run: `vendor/bin/sail artisan test --compact tests/Feature/PictureFileTest.php`
Expected: FAIL — `Route [party.pictures.show] not defined.`

- [x] **Step 3: Déclarer le limiteur**

Dans `app/Providers/AppServiceProvider.php`, sous la déclaration existante de `party-download` :

```php
        /**
         * Sharing a selection fetches one picture per file, so the ceiling sits
         * well above the download limiter. The key carries the party because
         * guests of one venue usually share a single wifi address.
         */
        RateLimiter::for('party-picture', function (Request $request): Limit {
            $party = (string) $request->route('party');

            return Limit::perMinute(240)->by($request->ip().':'.$party);
        });
```

- [x] **Step 4: Écrire le contrôleur**

Créer `app/Http/Controllers/PictureFileController.php` :

```php
<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Models\Picture;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PictureFileController extends Controller
{
    /**
     * Stream a picture from the application's own origin.
     *
     * The Web Share API needs real File objects, which means reading the bytes
     * in JavaScript. Pictures live on S3, another origin, where a canvas would
     * be tainted and a fetch response opaque, so the bytes have to come back
     * through the application.
     */
    public function __invoke(Party $party, Picture $picture): StreamedResponse
    {
        abort_if($party->hidePictures(), 403);

        $stream = Storage::disk('s3')->readStream($picture->path);

        abort_if($stream === null, 404);

        return response()->stream(
            function () use ($stream): void {
                set_time_limit(0);

                fpassthru($stream);
                fclose($stream);
            },
            200,
            [
                'Content-Type' => 'image/jpeg',
                'Content-Disposition' => 'inline; filename="photo-'.$picture->id.'.jpg"',
                'Cache-Control' => (string) config('filesystems.disks.s3.options.CacheControl'),
                'X-Accel-Buffering' => 'no',
            ],
        );
    }
}
```

- [x] **Step 5: Déclarer la route**

Dans `routes/web.php`, ajouter l'import puis la route dans le groupe `{party}`, juste après `party.download` :

```php
use App\Http\Controllers\PictureFileController;
```

```php
    Route::get('pictures/{picture}', PictureFileController::class)
        ->scopeBindings()
        ->middleware('throttle:party-picture')
        ->name('pictures.show');
```

`scopeBindings()` résout la photo via `$party->pictures()`, de sorte qu'un identifiant appartenant à une autre soirée donne 404 sans vérification manuelle.

- [x] **Step 6: Lancer les tests pour les voir passer**

Run: `vendor/bin/sail artisan test --compact tests/Feature/PictureFileTest.php`
Expected: PASS, 7 tests.

- [x] **Step 7: Formater et commiter**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app routes tests/Feature/PictureFileTest.php
git commit -m "feat(gallery): serve pictures from the application origin"
```

---

### Task 2 : Hook de partage

**Files:**
- Create: `resources/js/hooks/use-photo-share.ts`

**Interfaces:**
- Consumes: la route `party.pictures.show`, dont l'URL est construite par l'appelant et passée dans `SharePhoto.url`.
- Produces:

```ts
export type ShareStatus = 'idle' | 'preparing' | 'ready' | 'sharing';
export interface ShareProgress { done: number; total: number }
export interface SharePhoto { id: number; url: string }
export interface PhotoShare {
    supported: boolean;
    status: ShareStatus;
    progress: ShareProgress;
    refreshSupport: () => void;
    share: (photos: SharePhoto[]) => void;
}
export default function usePhotoShare(onError: (message: string) => void): PhotoShare
```

- [x] **Step 1: Écrire le hook**

Créer `resources/js/hooks/use-photo-share.ts` :

```ts
import { useCallback, useRef, useState } from 'react';

export type ShareStatus = 'idle' | 'preparing' | 'ready' | 'sharing';

export interface ShareProgress {
    done: number;
    total: number;
}

export interface SharePhoto {
    id: number;
    url: string;
}

export interface PhotoShare {
    supported: boolean;
    status: ShareStatus;
    progress: ShareProgress;
    refreshSupport: () => void;
    share: (photos: SharePhoto[]) => void;
}

/** Requests kept in flight at once, so a mobile link is not saturated. */
const CONCURRENCY = 3;

const NO_PROGRESS: ShareProgress = { done: 0, total: 0 };

const DOWNLOAD_ERROR = 'Le téléchargement des photos a échoué. Réessaie dans un instant.';

/**
 * Whether this browser can share files. The probe file matters: several
 * browsers expose navigator.share without accepting files at all.
 */
export function detectShareSupport(): boolean {
    if (typeof navigator === 'undefined' || typeof navigator.share !== 'function' || typeof navigator.canShare !== 'function') {
        return false;
    }

    try {
        return navigator.canShare({
            files: [new File([new Uint8Array(1)], 'photo.jpg', { type: 'image/jpeg' })],
        });
    } catch {
        return false;
    }
}

/**
 * Native sharing of a picture selection.
 *
 * The files are downloaded first, then handed to the share sheet. A share call
 * needs a transient user activation, which the download may outlive; when the
 * browser refuses for that reason the files stay in memory so the next tap
 * shares them without downloading anything again.
 */
export default function usePhotoShare(onError: (message: string) => void): PhotoShare {
    const [supported, setSupported] = useState<boolean>(() => detectShareSupport());
    const [status, setStatus] = useState<ShareStatus>('idle');
    const [progress, setProgress] = useState<ShareProgress>(NO_PROGRESS);

    const filesRef = useRef<File[]>([]);
    const idsRef = useRef<number[]>([]);

    const refreshSupport = useCallback(() => setSupported(detectShareSupport()), []);

    const openShareSheet = useCallback(async () => {
        setStatus('sharing');

        try {
            await navigator.share({ files: filesRef.current });

            filesRef.current = [];
            idsRef.current = [];
            setProgress(NO_PROGRESS);
            setStatus('idle');
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                filesRef.current = [];
                idsRef.current = [];
                setProgress(NO_PROGRESS);
                setStatus('idle');

                return;
            }

            setStatus('ready');
        }
    }, []);

    const download = useCallback(async (photos: SharePhoto[]): Promise<File[]> => {
        const files: File[] = new Array(photos.length);
        let next = 0;
        let done = 0;

        const worker = async (): Promise<void> => {
            while (next < photos.length) {
                const index = next++;
                const response = await fetch(photos[index].url);

                if (!response.ok) {
                    throw new Error(`Failed to download picture ${photos[index].id}`);
                }

                const blob = await response.blob();

                files[index] = new File([blob], `photo-${index + 1}.jpg`, { type: 'image/jpeg' });

                done += 1;
                setProgress({ done, total: photos.length });
            }
        };

        await Promise.all(Array.from({ length: Math.min(CONCURRENCY, photos.length) }, worker));

        return files;
    }, []);

    const share = useCallback(
        (photos: SharePhoto[]) => {
            const ids = photos.map((photo) => photo.id);
            const alreadyDownloaded =
                status === 'ready' &&
                filesRef.current.length === ids.length &&
                idsRef.current.every((id, index) => id === ids[index]);

            if (alreadyDownloaded) {
                void openShareSheet();

                return;
            }

            setStatus('preparing');
            setProgress({ done: 0, total: photos.length });

            void download(photos)
                .then((files) => {
                    filesRef.current = files;
                    idsRef.current = ids;

                    return openShareSheet();
                })
                .catch(() => {
                    filesRef.current = [];
                    idsRef.current = [];
                    setProgress(NO_PROGRESS);
                    setStatus('idle');
                    onError(DOWNLOAD_ERROR);
                });
        },
        [download, onError, openShareSheet, status],
    );

    return { supported, status, progress, refreshSupport, share };
}
```

- [x] **Step 2: Vérifier que le projet compile**

Run: `vendor/bin/sail npm run build`
Expected: build réussi, aucune erreur TypeScript.

Le hook n'a encore aucun appelant : rien d'autre n'est observable à ce stade, la couverture arrive avec la tâche 3, qui le branche et le teste au navigateur.

- [x] **Step 3: Commit**

```bash
git add resources/js/hooks/use-photo-share.ts
git commit -m "feat(gallery): add the native share hook for a picture selection"
```

---

### Task 3 : Bouton de la barre de sélection

**Files:**
- Modify: `resources/js/components/party/selection-bar.tsx`
- Modify: `resources/js/pages/party/gallery.tsx`
- Modify: `tests/Web/GalleryTest.php`

**Interfaces:**
- Consumes: `usePhotoShare` de la tâche 2, la route `party.pictures.show` de la tâche 1, `usePhotoSelection` existant.
- Produces: le bouton `[data-download-button]` de la barre basse, qui partage ou soumet le ZIP selon le support du navigateur.

- [x] **Step 1: Écrire les tests navigateur**

Ajouter à la fin de `tests/Web/GalleryTest.php`, avant la section d'authentification s'il y en a une :

```php
// ── Partage natif ─────────────────────────────────────────────────────────────

/**
 * Stubs the Web Share API and the picture fetch, so the browser test covers the
 * wiring without depending on real bytes sitting in the bucket. The route that
 * serves those bytes has its own coverage in tests/Feature/PictureFileTest.php.
 */
function stubWebShare(object $page, string $shareResult = 'resolve', int $fetchDelay = 0): object
{
    return $page->script(<<<JS
        window.fetch = () => new Promise((resolve) => setTimeout(
            () => resolve(new Response(new Blob(['x'], { type: 'image/jpeg' }))),
            {$fetchDelay},
        ));
        navigator.canShare = () => true;
        navigator.share = (data) => {
            document.body.dataset.sharedFiles = String(data.files.length);

            return '{$shareResult}' === 'resolve'
                ? Promise.resolve()
                : Promise.reject(new DOMException('cancelled', 'AbortError'));
        };
    JS);
}

it('shares the selection natively when the browser supports it', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    stubWebShare($page)
        ->click('Sélectionner')
        ->click('Tout sélectionner')
        ->click('[data-download-button]')
        ->assertAttribute('body', 'data-shared-files', '3')
        ->assertNoJavaScriptErrors();
});

it('keeps the download label while sharing', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    stubWebShare($page)
        ->click('Sélectionner')
        ->click('Tout sélectionner')
        ->assertSeeIn('[data-download-button]', 'Télécharger')
        ->assertNoJavaScriptErrors();
});

it('shows the download progress while the files are fetched', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    stubWebShare($page, 'resolve', 1500)
        ->click('Sélectionner')
        ->click('Tout sélectionner')
        ->click('[data-download-button]')
        ->assertPresent('[data-download-progress]')
        ->assertSeeIn('[data-download-button]', 'Téléchargement…')
        ->assertNoJavaScriptErrors();
});

it('keeps the selection usable when the share sheet is dismissed', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    stubWebShare($page, 'reject')
        ->click('Sélectionner')
        ->click('Tout sélectionner')
        ->click('[data-download-button]')
        ->assertSeeIn('[data-selection-count]', '2')
        ->assertSeeIn('[data-download-button]', 'Télécharger')
        ->assertNoJavaScriptErrors();
});

it('falls back to the zip form when the browser cannot share files', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->click('Sélectionner')
        ->click('Tout sélectionner')
        ->assertSeeIn('[data-download-button]', 'Télécharger le ZIP')
        ->assertAttribute('[data-download-button]', 'type', 'submit')
        ->assertNoJavaScriptErrors();
});
```

- [x] **Step 2: Lancer les tests pour les voir échouer**

Run: `vendor/bin/sail artisan test --compact tests/Web/GalleryTest.php --filter="share|zip form"`
Expected: FAIL — le sélecteur `[data-download-button]` n'existe pas encore.

- [x] **Step 3: Rendre la barre de sélection consciente du mode**

Dans `resources/js/components/party/selection-bar.tsx`, étendre les props et le bouton.

Remplacer l'interface `SelectionBarProps` par :

```tsx
interface SelectionBarProps {
    open: boolean;
    count: number;
    stack: StackPhoto[];
    selectedIds: number[];
    action: string;
    csrfToken: string;
    mode: 'share' | 'zip';
    shareStatus: ShareStatus;
    shareProgress: ShareProgress;
    onShare: () => void;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}
```

Ajouter l'import du hook en tête du fichier :

```tsx
import type { ShareProgress, ShareStatus } from '@/hooks/use-photo-share';
```

Étendre la signature du composant :

```tsx
export default function SelectionBar({
    open,
    count,
    stack,
    selectedIds,
    action,
    csrfToken,
    mode,
    shareStatus,
    shareProgress,
    onShare,
    onSubmit,
}: SelectionBarProps) {
```

Puis, juste avant le `return`, calculer le libellé et l'état du bouton :

```tsx
    const sharing = mode === 'share';
    const busy = sharing && (shareStatus === 'preparing' || shareStatus === 'sharing');
    const label = sharing
        ? shareStatus === 'preparing'
            ? `Téléchargement… ${shareProgress.done}/${shareProgress.total}`
            : 'Télécharger'
        : count > 1
          ? 'Télécharger le ZIP'
          : 'Télécharger';
    const ratio = shareProgress.total === 0 ? 0 : shareProgress.done / shareProgress.total;
```

Remplacer le `<button>` existant par :

```tsx
                <button
                    data-download-button
                    type={sharing ? 'button' : 'submit'}
                    onClick={sharing ? onShare : undefined}
                    disabled={count === 0 || busy}
                    className="relative overflow-hidden rounded-full px-5 py-3 text-sm whitespace-nowrap transition-opacity disabled:cursor-default enabled:cursor-pointer enabled:bg-cream enabled:text-ink disabled:bg-cream/20 disabled:text-cream/50"
                >
                    {label}
                    {sharing && shareStatus === 'preparing' && (
                        <span
                            data-download-progress
                            className="absolute inset-x-0 bottom-0 h-[3px] origin-left bg-ink/40 transition-transform duration-200"
                            style={{ transform: `scaleX(${ratio})` }}
                        />
                    )}
                </button>
```

- [x] **Step 4: Brancher le hook dans la page**

Dans `resources/js/pages/party/gallery.tsx`, ajouter l'import :

```tsx
import usePhotoShare from '@/hooks/use-photo-share';
```

Après `const selection = usePhotoSelection(loadedIds);`, instancier le hook et calculer le mode :

```tsx
    const photoShare = usePhotoShare(flash);

    const pictureUrl = useCallback(
        (id: number) => `/${encodeURIComponent(party.name)}/pictures/${id}`,
        [party.name],
    );

    const shareMode = photoShare.supported && !hidePictures;
```

`flash` étant déclaré plus bas dans le fichier, déplacer la déclaration de `photoShare` juste après celle de `flash` pour respecter l'ordre des dépendances.

Ajouter le gestionnaire de partage à côté de `onSubmitDownload` :

```tsx
    const onShare = useCallback(() => {
        if (selection.count > maxDownloadPictures) {
            flash(`Sélection trop grande : ${maxDownloadPictures} photos maximum par téléchargement`);

            return;
        }

        photoShare.share(selection.selectedIds.map((id) => ({ id, url: pictureUrl(id) })));
    }, [flash, maxDownloadPictures, photoShare, pictureUrl, selection.count, selection.selectedIds]);
```

Rafraîchir la détection à l'entrée en mode sélection, en remplaçant la prop passée à `GalleryStickyBar` :

```tsx
                onEnterSelect={() => {
                    photoShare.refreshSupport();
                    selection.enter();
                }}
```

Enfin, passer les nouvelles props à `SelectionBar` :

```tsx
                mode={shareMode ? 'share' : 'zip'}
                shareStatus={photoShare.status}
                shareProgress={photoShare.progress}
                onShare={onShare}
```

- [x] **Step 5: Construire et lancer les tests navigateur**

Run: `vendor/bin/sail npm run build && vendor/bin/sail artisan test --compact tests/Web/GalleryTest.php`
Expected: PASS, tous les tests du fichier, anciens comme nouveaux.

- [x] **Step 6: Lancer la suite complète**

Run: `vendor/bin/sail artisan test --compact`
Expected: PASS.

- [x] **Step 7: Commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add resources/js tests/Web/GalleryTest.php
git commit -m "feat(gallery): share the selection natively when the browser can"
```

---

## Écarts constatés à l'exécution

- **En-tête de cache.** Symfony réordonne les directives de `Cache-Control`, si
  bien qu'une comparaison littérale avec la valeur de configuration échoue. Le
  test compare l'ensemble des directives, trié.
- **Sondes des tests navigateur.** `assertAttribute` ne résout pas les
  sélecteurs par attribut ni `body` ; le stub écrit donc son résultat dans
  `document.title` (`shared:3`), lu par `assertTitleContains`. Un script évalué
  ne doit pas non plus rendre une fonction, que Playwright ne sait pas
  sérialiser : le stub se termine par `true;`.

## Points de vigilance

- **Activation utilisateur.** `navigator.share()` doit partir d'un geste. Le téléchargement peut consommer l'activation transitoire, que Chrome limite à quelques secondes ; c'est exactement le cas que couvre l'état `ready`, où les fichiers restent en mémoire et le tap suivant ouvre la feuille sans rien re-télécharger.
- **`AbortError` n'est pas une erreur.** Fermer la feuille de partage est un geste ordinaire : aucun toast, aucun message, la sélection reste telle quelle.
- **Mémoire.** Une grande sélection tient entièrement en mémoire le temps du partage. Les fichiers sont relâchés dès la fermeture de la feuille, et le plafond `maxDownloadPictures` reste la seule borne, comme décidé dans la spec.
- **Tests navigateur et octets réels.** Les tests stubent `window.fetch` : le navigateur de test n'a pas de bucket derrière lui. Ce que le stub ne couvre pas — les octets, les en-têtes, le cloisonnement entre parties — est couvert par `tests/Feature/PictureFileTest.php`.
- **`scopeBindings()` et le nom de party.** La party se résout par son `name` et la photo par son identifiant, dans la relation `pictures` de la party : une photo d'une autre soirée donne 404 sans code de vérification.
