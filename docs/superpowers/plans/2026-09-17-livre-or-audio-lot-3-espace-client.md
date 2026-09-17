# Livre d'or audio — Lot 3 : espace client, l'annonce

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que le client enregistre son annonce depuis son navigateur, la réécoute, la soumette au studio et suive son état, sur la page `/audiobook/{uuid}` que le lot 1 a ouverte en gabarit.

**Architecture:** Une seule page Inertia. Le contrôleur envoie l'état de l'annonce, la page rend le bloc correspondant. L'enregistrement se fait par `MediaRecorder` dans un hook qui possède le flux, le minuteur et les niveaux ; la page ne connaît que l'état résultant. Le fichier part tel que le navigateur le produit — la conversion MP3 est le lot 4.

**Tech Stack:** Laravel 12, Inertia 2 + React 19, Tailwind 4, Pest 4, disque `s3`.

**Spec:** `docs/superpowers/specs/2026-09-17-livre-or-audio.md`

**Maquette :** projet Claude Design `2bfaa011-8a08-4720-b4b8-7598dac46ba1`, fichier `Livre d'or audio.dc.html`, phase « enregistrement ». Les textes exacts ci-dessous en sont tirés ; la maquette reste la référence pour l'espacement et la hiérarchie typographique.

**Issues :** épic #51, lot #63, sous-tâches #64 à #67.

## Global Constraints

- **Toutes les commandes passent par Sail.** Jamais `php`/`artisan`/`npm` nus.
- **Tests ciblés** par chemin de fichier, jamais `--filter` : `--filter` attrape `Tests\Feature\Console\CreatePartyTest`, qui porte un échec préexistant sans rapport (voir le plan du lot 1).
- **Après toute modification de PHP :** `vendor/bin/sail bin pint --dirty --format agent`.
- **Les composants publics vivent dans `resources/js/components/party/`** et n'utilisent **que** des utilitaires Tailwind et les jetons du thème. **Aucun import de `@/components/ui/*`, aucun shadcn, aucun Radix** — ils ne sont compilés que dans `admin.css`, un composant public qui les importe perd silencieusement ses styles.
- **Style TypeScript sous `resources/js/components/party/`, `pages/party/`, `hooks/` :** guillemets simples, points-virgules, indentation 4 espaces, largeur 120. Prettier n'est pas une dépendance du projet — ne pas le lancer sans ces réglages.
- **Jetons disponibles :** `bg-surface`, `bg-cream`, `bg-ink`, `bg-placeholder`, `text-ink`, `text-cream`, `text-muted`, `text-subtle`, `border-line`, `font-sans`, `font-serif`. La tâche 2 en ajoute deux : `accent` et `valid`.
- **Les textes visibles sont en français**, donnés mot pour mot dans les tâches. Les tests s'appuient dessus, ne pas les reformuler. Les commentaires de code restent en anglais.
- **Durée maximale d'une annonce : 90 secondes.** Coupure automatique côté navigateur.
- **Statuts :** `draft`, `pending`, `validated`, `rejected` — valeurs exactes.
- **PHP :** types de retour explicites, accolades toujours, PHPDoc plutôt que commentaires en ligne, casts dans `casts()`.

---

## File Structure

**Créés**

| Fichier | Responsabilité |
| --- | --- |
| `app/Http/Requests/StoreAudioGreetingRequest.php` | Validation du fichier déposé |
| `app/Http/Requests/SubmitAudioGreetingRequest.php` | Validation de la note pour le studio |
| `app/Http/Controllers/AudioGreetingController.php` | Dépôt et soumission |
| `resources/js/pages/party/audiobook.tsx` | *(existe en gabarit)* la page complète |
| `resources/js/components/party/audiobook-header.tsx` | Bandeau de titre et ligne d'événement |
| `resources/js/components/party/audiobook-steps.tsx` | Le compteur en trois étapes |
| `resources/js/components/party/audiobook-recorder.tsx` | La carte sombre : statut, waveform, minuteur, boutons |
| `resources/js/components/party/audiobook-review.tsx` | Le panneau « Avant d'envoyer » et sa note |
| `resources/js/components/party/audiobook-status.tsx` | Les panneaux `pending`, `validated`, `rejected` |
| `resources/js/components/party/audiobook-onboarding.tsx` | La modale « Comment ça se passe » |
| `resources/js/components/party/audiobook-tips.tsx` | Les « Trois repères » |
| `resources/js/hooks/use-audio-recorder.ts` | Flux micro, minuteur, niveaux, take |
| `tests/Feature/AudioGreetingSubmissionTest.php` | Dépôt, soumission, réenregistrement |
| `tests/Web/AudiobookTest.php` | Parcours navigateur |

**Modifiés**

| Fichier | Changement |
| --- | --- |
| `app/Http/Controllers/AudiobookController.php` | Envoie l'état de l'annonce |
| `app/Models/AudioGreeting.php` | `playbackUrl()` |
| `resources/css/app.css` | Jetons `accent` et `valid` |
| `routes/web.php` | Deux routes d'écriture |
| `tests/Feature/AudioGuestbookAccessTest.php` | Props de la page |

---

## Task 1: Le contrat de la page, le dépôt et la soumission

Issue #66 (la moitié serveur ; sa moitié client est la tâche 4).

**Files:**
- Create: `app/Http/Requests/StoreAudioGreetingRequest.php`
- Create: `app/Http/Requests/SubmitAudioGreetingRequest.php`
- Create: `app/Http/Controllers/AudioGreetingController.php`
- Create: `tests/Feature/AudioGreetingSubmissionTest.php`
- Modify: `app/Http/Controllers/AudiobookController.php`
- Modify: `app/Models/AudioGreeting.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/AudioGuestbookAccessTest.php`

**Interfaces:**
- Consumes: `Party::greeting()`, `GreetingStatus`, la route `audiobook.show` du lot 1.
- Produces: routes nommées `audiobook.greeting.store` et `audiobook.greeting.submit` ; prop `greeting` de la page ; `AudioGreeting::playbackUrl(): ?string`.

**Le contrat de la page.** `AudiobookController::show()` envoie :

```php
'party' => [
    'title' => string,
    'event_line' => string|null,   // « 12 juin 2026 », null sans event_at
],
'greeting' => null | [
    'status' => 'draft'|'pending'|'validated'|'rejected',
    'url' => string|null,          // playbackUrl()
    'duration_seconds' => int|null,
    'client_note' => string|null,
    'rejection_reason' => string|null,
    'submitted_at' => string|null, // ISO 8601
],
```

**Pourquoi `playbackUrl()` et pas `url()`.** `url()` ne rend que le MP3, produit par le job du lot 4 ; tant que ce lot n'existe pas, une annonce soumise serait inécoutable par son propre auteur. `playbackUrl()` rend le MP3 s'il existe, l'original sinon — le navigateur sait relire ce qu'il vient d'enregistrer. `url()` reste le lien du studio, qui exige le MP3.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/AudioGreetingSubmissionTest.php` :

```php
<?php

use App\Enums\GreetingStatus;
use App\Models\AudioGreeting;
use App\Models\Party;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function audiobookParty(): Party
{
    return Party::factory()->withAudioGuestbook()->create();
}

it('stores a recording as a draft', function () {
    Storage::fake('s3');
    $party = audiobookParty();

    $this->post('/audiobook/'.$party->client_uuid.'/annonce', [
        'recording' => UploadedFile::fake()->create('take.webm', 400, 'audio/webm'),
    ])->assertRedirect();

    $greeting = $party->fresh()->greeting;

    expect($greeting)->not->toBeNull()
        ->and($greeting->status)->toBe(GreetingStatus::Draft)
        ->and($greeting->original_path)->toStartWith('parties/'.$party->id.'/audio/');

    Storage::disk('s3')->assertExists($greeting->original_path);
});

it('replaces the previous take rather than piling them up', function () {
    Storage::fake('s3');
    $party = audiobookParty();
    $first = AudioGreeting::factory()->for($party)->create();
    Storage::disk('s3')->put($first->original_path, 'old');

    $this->post('/audiobook/'.$party->client_uuid.'/annonce', [
        'recording' => UploadedFile::fake()->create('take.webm', 400, 'audio/webm'),
    ])->assertRedirect();

    expect($party->fresh()->greeting->id)->toBe($first->id);
    Storage::disk('s3')->assertMissing($first->original_path);
});

it('refuses a recording that is not audio', function () {
    Storage::fake('s3');
    $party = audiobookParty();

    $this->post('/audiobook/'.$party->client_uuid.'/annonce', [
        'recording' => UploadedFile::fake()->image('selfie.jpg'),
    ])->assertSessionHasErrors('recording');

    expect($party->fresh()->greeting)->toBeNull();
});

it('submits a draft for validation with the studio note', function () {
    $party = audiobookParty();
    AudioGreeting::factory()->for($party)->create();

    $this->post('/audiobook/'.$party->client_uuid.'/annonce/soumettre', [
        'client_note' => 'Le prénom se prononce « Lo-ic ».',
    ])->assertRedirect();

    expect($party->fresh()->greeting)
        ->status->toBe(GreetingStatus::Pending)
        ->client_note->toBe('Le prénom se prononce « Lo-ic ».')
        ->submitted_at->not->toBeNull();
});

it('submits without a note', function () {
    $party = audiobookParty();
    AudioGreeting::factory()->for($party)->create();

    $this->post('/audiobook/'.$party->client_uuid.'/annonce/soumettre')
        ->assertRedirect();

    expect($party->fresh()->greeting->status)->toBe(GreetingStatus::Pending);
});

it('refuses a note longer than 120 characters', function () {
    $party = audiobookParty();
    AudioGreeting::factory()->for($party)->create();

    $this->post('/audiobook/'.$party->client_uuid.'/annonce/soumettre', [
        'client_note' => str_repeat('a', 121),
    ])->assertSessionHasErrors('client_note');
});

it('refuses to submit when there is nothing recorded', function () {
    $party = audiobookParty();

    $this->post('/audiobook/'.$party->client_uuid.'/annonce/soumettre')
        ->assertNotFound();
});

it('sends a rejected greeting back to draft when a new take is stored', function () {
    Storage::fake('s3');
    $party = audiobookParty();
    AudioGreeting::factory()->for($party)->rejected('Son saturé')->create();

    $this->post('/audiobook/'.$party->client_uuid.'/annonce', [
        'recording' => UploadedFile::fake()->create('take.webm', 400, 'audio/webm'),
    ])->assertRedirect();

    expect($party->fresh()->greeting)
        ->status->toBe(GreetingStatus::Draft)
        ->rejection_reason->toBeNull()
        ->submitted_at->toBeNull();
});

it('reopens a validated greeting the same way', function () {
    Storage::fake('s3');
    $party = audiobookParty();
    AudioGreeting::factory()->for($party)->validated()->create();

    $this->post('/audiobook/'.$party->client_uuid.'/annonce', [
        'recording' => UploadedFile::fake()->create('take.webm', 400, 'audio/webm'),
    ])->assertRedirect();

    expect($party->fresh()->greeting)
        ->status->toBe(GreetingStatus::Draft)
        ->validated_at->toBeNull()
        ->mp3_path->toBeNull();
});

it('does not accept a recording for a party without the module', function () {
    Storage::fake('s3');
    $party = Party::factory()->create(['has_audio_guestbook' => false]);

    $this->post('/audiobook/'.$party->client_uuid.'/annonce', [
        'recording' => UploadedFile::fake()->create('take.webm', 400, 'audio/webm'),
    ])->assertNotFound();
});
```

Ajouter à `tests/Feature/AudioGuestbookAccessTest.php` :

```php
it('sends the event identity and no greeting to a client who has not recorded', function () {
    $party = Party::factory()->withAudioGuestbook()->eventAt(now()->setDate(2026, 6, 12))->create([
        'display_name' => 'Camille & Antoine',
    ]);

    $this->get('/audiobook/'.$party->client_uuid)
        ->assertInertia(fn ($page) => $page
            ->component('party/audiobook')
            ->where('party.title', 'Camille & Antoine')
            ->where('party.event_line', '12 juin 2026')
            ->where('greeting', null)
        );
});

it('sends the greeting state to a client who has recorded', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    AudioGreeting::factory()->for($party)->rejected('Son saturé')->create();

    $this->get('/audiobook/'.$party->client_uuid)
        ->assertInertia(fn ($page) => $page
            ->where('greeting.status', 'rejected')
            ->where('greeting.rejection_reason', 'Son saturé')
            ->has('greeting.url')
        );
});
```

Importer `App\Models\AudioGreeting` en tête de ce fichier.

- [ ] **Step 2: Run the tests to verify they fail**

```
vendor/bin/sail artisan test --compact tests/Feature/AudioGreetingSubmissionTest.php tests/Feature/AudioGuestbookAccessTest.php
```

Attendu : ÉCHEC, les routes n'existent pas et la page n'envoie pas `greeting`.

- [ ] **Step 3: Add `playbackUrl()` to `AudioGreeting`**

```php
    /**
     * Signed URL the client listens to in their own space.
     *
     * The MP3 once the conversion has run, the original take until then: the
     * browser can replay what it just recorded, and leaving the client unable
     * to hear their own submission while the job is queued would be worse than
     * serving a format the handset happens not to read.
     */
    public function playbackUrl(): ?string
    {
        $path = $this->mp3_path ?: $this->original_path;

        if (! $path) {
            return null;
        }

        return SignedStorageUrl::for($path);
    }
```

- [ ] **Step 4: Write the form requests**

`app/Http/Requests/StoreAudioGreetingRequest.php` :

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAudioGreetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The browser decides the container on its own — WebM/Opus on Chrome and
     * Firefox, MP4/AAC on Safari — so the rule names every one of them rather
     * than a single expected type.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recording' => [
                'required',
                'file',
                'mimetypes:audio/webm,audio/ogg,audio/mp4,audio/mpeg,video/webm,video/mp4',
                'max:25600',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recording.required' => 'Aucun enregistrement reçu.',
            'recording.mimetypes' => "Ce fichier n'est pas un enregistrement audio.",
            'recording.max' => "L'enregistrement dépasse la taille autorisée.",
        ];
    }
}
```

`app/Http/Requests/SubmitAudioGreetingRequest.php` :

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubmitAudioGreetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_note' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_note.max' => 'La note ne peut pas dépasser 120 caractères.',
        ];
    }
}
```

- [ ] **Step 5: Write the controller**

`app/Http/Controllers/AudioGreetingController.php` :

```php
<?php

namespace App\Http\Controllers;

use App\Enums\GreetingStatus;
use App\Http\Requests\StoreAudioGreetingRequest;
use App\Http\Requests\SubmitAudioGreetingRequest;
use App\Models\AudioGreeting;
use App\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class AudioGreetingController extends Controller
{
    /**
     * Take the client's recording.
     *
     * A party keeps a single greeting: a new take replaces the previous one,
     * files included, and sends the record back to `draft` whatever it was.
     * Re-recording is offered from every state, validated included.
     */
    public function store(StoreAudioGreetingRequest $request, Party $party): RedirectResponse
    {
        $greeting = $party->greeting()->first() ?? new AudioGreeting(['party_id' => $party->id]);

        $previousPaths = array_filter([$greeting->original_path, $greeting->mp3_path]);

        $path = $request->file('recording')->store('parties/'.$party->id.'/audio', 's3');

        $greeting->fill([
            'original_path' => $path,
            'mp3_path' => null,
            'duration_seconds' => null,
            'status' => GreetingStatus::Draft,
            'rejection_reason' => null,
            'submitted_at' => null,
            'validated_at' => null,
        ])->save();

        if ($previousPaths !== []) {
            Storage::disk('s3')->delete($previousPaths);
        }

        return back();
    }

    /**
     * Hand the take to the studio. The note travels with it and is kept even
     * when the client leaves it empty, so a second submission never inherits
     * the note of the first.
     */
    public function submit(SubmitAudioGreetingRequest $request, Party $party): RedirectResponse
    {
        $greeting = $party->greeting()->first();

        abort_unless($greeting instanceof AudioGreeting, 404);

        $greeting->update([
            'client_note' => $request->validated('client_note'),
            'status' => GreetingStatus::Pending,
            'submitted_at' => now(),
        ]);

        return back();
    }
}
```

- [ ] **Step 6: Extend `AudiobookController::show()`**

```php
    public function show(Party $party): Response
    {
        $greeting = $party->greeting()->first();

        return Inertia::render('party/audiobook', [
            'party' => [
                'title' => $party->displayTitle(),
                'event_line' => $party->event_at?->locale('fr')->isoFormat('D MMMM YYYY'),
            ],
            'greeting' => $greeting instanceof AudioGreeting ? [
                'status' => $greeting->status->value,
                'url' => $greeting->playbackUrl(),
                'duration_seconds' => $greeting->duration_seconds,
                'client_note' => $greeting->client_note,
                'rejection_reason' => $greeting->rejection_reason,
                'submitted_at' => $greeting->submitted_at?->toIso8601String(),
            ] : null,
        ]);
    }
```

Importer `App\Models\AudioGreeting`.

- [ ] **Step 7: Declare the routes**

Sous la route `audiobook.show`, dans le même esprit :

```php
Route::post('audiobook/{party:client_uuid}/annonce', [AudioGreetingController::class, 'store'])
    ->middleware(['audiobook', 'throttle:audiobook-write'])
    ->name('audiobook.greeting.store');

Route::post('audiobook/{party:client_uuid}/annonce/soumettre', [AudioGreetingController::class, 'submit'])
    ->middleware(['audiobook', 'throttle:audiobook-write'])
    ->name('audiobook.greeting.submit');
```

Et le limiteur correspondant dans `AppServiceProvider::boot()`, keyé comme ses voisins puisqu'il ne s'agit plus d'énumération mais d'abus sur un espace déjà atteint :

```php
        /**
         * Recording is a rare gesture, and the uuid is already known by the
         * time these are reached: keyed by address and party, like the
         * gallery's own write endpoints.
         */
        RateLimiter::for('audiobook-write', function (Request $request): Limit {
            return Limit::perMinute(20)->by($request->ip().':'.$request->route('party'));
        });
```

- [ ] **Step 8: Run the tests**

```
vendor/bin/sail artisan test --compact tests/Feature/AudioGreetingSubmissionTest.php tests/Feature/AudioGuestbookAccessTest.php
```

Attendu : SUCCÈS.

- [ ] **Step 9: Format and commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add -A
git commit -m "feat(audio): accepte le dépôt et la soumission de l'annonce

Refs #66"
```

---

## Task 2: Jetons de couleur et squelette de la page

Issue #64, première moitié.

**Files:**
- Modify: `resources/css/app.css`
- Create: `resources/js/components/party/audiobook-header.tsx`
- Create: `resources/js/components/party/audiobook-steps.tsx`
- Modify: `resources/js/pages/party/audiobook.tsx`

**Interfaces:**
- Consumes: les props `party` et `greeting` de la tâche 1.
- Produces: `AudiobookHeader({ title, eventLine })`, `AudiobookSteps({ current })` où `current` vaut 0, 1 ou 2 ; type `GreetingState` exporté par la page.

- [ ] **Step 1: Add the two missing tokens**

Dans le bloc `@theme` de `resources/css/app.css`, à la suite des couleurs existantes :

```css
    --color-accent: #a8452c;
    --color-valid: #4f6b4a;
```

`accent` est le rouge de l'enregistrement en cours, `valid` le vert de la validation studio. Les deux viennent de la maquette et n'existaient pas dans le thème de la galerie.

- [ ] **Step 2: Write the header**

`resources/js/components/party/audiobook-header.tsx` — bandeau centré, séparé du corps par une ligne :

```tsx
interface AudiobookHeaderProps {
    title: string;
    eventLine: string | null;
}

/**
 * The page's masthead: a small private-space eyebrow, the event title in the
 * serif face, and the date when the party carries one.
 */
export default function AudiobookHeader({ title, eventLine }: AudiobookHeaderProps) {
    return (
        <header className="flex flex-col items-center gap-4 border-b border-line px-5 py-13 text-center sm:px-8 lg:px-16 lg:py-26">
            <p className="font-mono text-[10px] tracking-[0.22em] text-subtle uppercase">
                Livre d'or audio · espace privé
            </p>
            <h1 className="max-w-[22ch] font-serif text-[clamp(34px,6.5vw,72px)] leading-[0.98] tracking-[-0.015em] text-pretty">
                {title}
            </h1>
            <div className="h-px w-[34px] bg-ink/20" />
            {eventLine && (
                <p className="text-[13px] tracking-[0.12em] text-muted uppercase">{eventLine}</p>
            )}
        </header>
    );
}
```

- [ ] **Step 3: Write the step counter**

`resources/js/components/party/audiobook-steps.tsx` :

```tsx
const STEPS = ['Enregistrer', 'Validation studio', 'En messagerie le jour J'];

interface AudiobookStepsProps {
    /** 0 while recording or reviewing, 1 once submitted, 2 once validated. */
    current: number;
}

export default function AudiobookSteps({ current }: AudiobookStepsProps) {
    return (
        <ol className="grid grid-cols-3 gap-2.5">
            {STEPS.map((step, index) => (
                <li key={step} className="flex flex-col gap-2.5">
                    <span
                        className={`h-0.5 rounded-sm ${index <= current ? 'bg-ink' : 'bg-ink/15'}`}
                        aria-hidden="true"
                    />
                    <span
                        className={`text-[11px] leading-[1.4] tracking-[0.1em] uppercase ${
                            index <= current ? 'text-ink' : 'text-subtle'
                        }`}
                    >
                        {step}
                    </span>
                </li>
            ))}
        </ol>
    );
}
```

- [ ] **Step 4: Rebuild the page around them**

`resources/js/pages/party/audiobook.tsx` remplace le gabarit du lot 1 :

```tsx
import { Head } from '@inertiajs/react';

import AudiobookHeader from '@/components/party/audiobook-header';
import AudiobookSteps from '@/components/party/audiobook-steps';
import PartyLayout from '@/layouts/party-layout';

export type GreetingStatus = 'draft' | 'pending' | 'validated' | 'rejected';

export interface GreetingState {
    status: GreetingStatus;
    url: string | null;
    duration_seconds: number | null;
    client_note: string | null;
    rejection_reason: string | null;
    submitted_at: string | null;
}

interface AudiobookProps {
    party: {
        title: string;
        event_line: string | null;
    };
    greeting: GreetingState | null;
}

const STEP_OF: Record<GreetingStatus, number> = {
    draft: 0,
    rejected: 0,
    pending: 1,
    validated: 2,
};

export default function Audiobook({ party, greeting }: AudiobookProps) {
    const step = greeting ? STEP_OF[greeting.status] : 0;

    return (
        <PartyLayout>
            <Head title={party.title} />
            <AudiobookHeader title={party.title} eventLine={party.event_line} />
            <div className="mx-auto flex w-full max-w-[780px] flex-1 flex-col gap-7 px-5 py-7 sm:px-10 lg:gap-10 lg:py-13">
                <AudiobookSteps current={step} />
            </div>
        </PartyLayout>
    );
}
```

- [ ] **Step 5: Build and check the page renders**

```
vendor/bin/sail npm run build
vendor/bin/sail artisan test --compact tests/Feature/AudioGuestbookAccessTest.php
```

Attendu : build SUCCÈS, tests toujours verts.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat(audio): pose le bandeau et le compteur d'étapes de l'espace client

Refs #64"
```

---

## Task 3: L'enregistreur

Issue #65.

**Files:**
- Create: `resources/js/hooks/use-audio-recorder.ts`
- Create: `resources/js/components/party/audiobook-recorder.tsx`
- Modify: `resources/js/pages/party/audiobook.tsx`

**Interfaces:**
- Produces: `useAudioRecorder({ maxSeconds })` renvoyant `{ phase, elapsed, levels, take, error, start, stop, reset }` où `phase` vaut `'idle' | 'recording' | 'ready'` et `take` vaut `{ blob, url, durationSeconds } | null`.

**Ce que le hook possède, et pourquoi.** Le flux micro, le `MediaRecorder`, l'`AnalyserNode`, la boucle d'animation et le minuteur vivent tous ensemble : ils partagent un cycle de vie et doivent être libérés d'un seul geste. La page n'en voit que le résultat. Sans cette frontière, un composant démonté en cours d'enregistrement laisserait le micro allumé — la pastille rouge de l'onglet reste, et c'est le genre de détail qui fait désinstaller une application.

- [ ] **Step 1: Write the hook**

`resources/js/hooks/use-audio-recorder.ts` :

```ts
import { useCallback, useEffect, useRef, useState } from 'react';

export type RecorderPhase = 'idle' | 'recording' | 'ready';

export interface RecorderTake {
    blob: Blob;
    url: string;
    durationSeconds: number;
}

interface UseAudioRecorderOptions {
    maxSeconds: number;
}

/** How many bars the waveform keeps on screen. */
const BARS = 34;

/** Milliseconds between two level samples; below this the bars only flicker. */
const SAMPLE_INTERVAL = 70;

/**
 * Owns the microphone for the whole life of a take: the stream, the recorder,
 * the analyser feeding the waveform, and the clock that stops at the ceiling.
 * Everything is released together, so a component unmounted mid-recording
 * never leaves the microphone open.
 */
export default function useAudioRecorder({ maxSeconds }: UseAudioRecorderOptions) {
    const [phase, setPhase] = useState<RecorderPhase>('idle');
    const [elapsed, setElapsed] = useState(0);
    const [levels, setLevels] = useState<number[]>([]);
    const [take, setTake] = useState<RecorderTake | null>(null);
    const [error, setError] = useState<string | null>(null);

    const stream = useRef<MediaStream | null>(null);
    const recorder = useRef<MediaRecorder | null>(null);
    const context = useRef<AudioContext | null>(null);
    const analyser = useRef<AnalyserNode | null>(null);
    const frame = useRef<number | null>(null);
    const chunks = useRef<Blob[]>([]);
    const takeUrl = useRef<string | null>(null);

    const release = useCallback(() => {
        if (frame.current !== null) {
            cancelAnimationFrame(frame.current);
            frame.current = null;
        }

        stream.current?.getTracks().forEach((track) => track.stop());
        stream.current = null;

        void context.current?.close();
        context.current = null;
        analyser.current = null;
    }, []);

    useEffect(() => {
        return () => {
            release();

            if (takeUrl.current) {
                URL.revokeObjectURL(takeUrl.current);
            }
        };
    }, [release]);

    const stop = useCallback(() => {
        recorder.current?.state !== 'inactive' && recorder.current?.stop();
    }, []);

    const start = useCallback(async () => {
        setError(null);

        let media: MediaStream;

        try {
            media = await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch {
            setError("Nous n'avons pas accès au micro. Autorisez-le dans votre navigateur, puis réessayez.");

            return;
        }

        if (takeUrl.current) {
            URL.revokeObjectURL(takeUrl.current);
            takeUrl.current = null;
        }

        stream.current = media;
        chunks.current = [];
        setTake(null);
        setLevels([]);
        setElapsed(0);
        setPhase('recording');

        context.current = new AudioContext();
        analyser.current = context.current.createAnalyser();
        analyser.current.fftSize = 512;
        context.current.createMediaStreamSource(media).connect(analyser.current);

        const samples = new Uint8Array(analyser.current.frequencyBinCount);
        const startedAt = Date.now();
        let lastSample = 0;

        recorder.current = new MediaRecorder(media);
        recorder.current.ondataavailable = (event) => {
            if (event.data.size > 0) {
                chunks.current.push(event.data);
            }
        };
        recorder.current.onstop = () => {
            const seconds = Math.max(1, (Date.now() - startedAt) / 1000);
            const blob = new Blob(chunks.current, { type: chunks.current[0]?.type ?? 'audio/webm' });
            const url = URL.createObjectURL(blob);

            takeUrl.current = url;
            release();
            setTake({ blob, url, durationSeconds: seconds });
            setElapsed(seconds);
            setPhase('ready');
        };
        recorder.current.start();

        const loop = () => {
            const seconds = (Date.now() - startedAt) / 1000;

            if (seconds >= maxSeconds) {
                stop();

                return;
            }

            if (Date.now() - lastSample > SAMPLE_INTERVAL && analyser.current) {
                lastSample = Date.now();
                analyser.current.getByteTimeDomainData(samples);

                let peak = 0;

                for (const sample of samples) {
                    peak = Math.max(peak, Math.abs(sample - 128) / 128);
                }

                setElapsed(seconds);
                setLevels((previous) => [...previous, Math.min(1, 0.06 + peak * 2.2)].slice(-BARS));
            }

            frame.current = requestAnimationFrame(loop);
        };

        frame.current = requestAnimationFrame(loop);
    }, [maxSeconds, release, stop]);

    const reset = useCallback(() => {
        if (takeUrl.current) {
            URL.revokeObjectURL(takeUrl.current);
            takeUrl.current = null;
        }

        setTake(null);
        setLevels([]);
        setElapsed(0);
        setPhase('idle');
    }, []);

    return { phase, elapsed, levels, take, error, start, stop, reset, bars: BARS };
}
```

- [ ] **Step 2: Write the recorder card**

`resources/js/components/party/audiobook-recorder.tsx` — la carte sombre de la maquette. Elle ne décide de rien : elle reçoit l'état et rend les boutons correspondants.

Textes exacts : `Prêt à enregistrer`, `Enregistrement en cours`, `À relire`, `En attente de validation`, `Validé` pour la pastille de statut ; `Commencer l'enregistrement`, `Arrêter`, `Réenregistrer`, `Réécouter`, `Pause`, `Écouter le message` pour les boutons ; `sur 1:30 max`, `durée du message`, `30 à 60 s conseillées` pour l'indication de durée.

Structure : `bg-ink text-cream rounded-[4px]`, colonne centrée, dans l'ordre — pastille de statut, waveform de 34 barres, minuteur (`font-serif`, `clamp(34px,6vw,52px)`), rangée de boutons, message d'erreur micro le cas échéant. Les barres sont des `<span>` de hauteur `8 + valeur * 92` pour cent, `bg-accent` pendant l'enregistrement, `bg-cream` ou `bg-cream/20` selon la progression de lecture ensuite.

- [ ] **Step 3: Wire the recorder into the page**

La page instancie `useAudioRecorder({ maxSeconds: 90 })` et rend `AudiobookRecorder`. Le bouton `Réenregistrer` appelle `reset()`.

- [ ] **Step 4: Verify in the browser**

```
vendor/bin/sail npm run build
```

Puis ouvrir `/audiobook/{uuid}` d'une party de test et vérifier à la main : le micro se demande au clic, la waveform bouge, le minuteur coupe à 1:30, la réécoute fonctionne, et quitter la page éteint la pastille d'enregistrement de l'onglet.

Un test navigateur Pest ne peut pas accorder le micro ; c'est la limite assumée, la tâche 5 couvre le reste du parcours.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat(audio): enregistre l'annonce depuis le navigateur

Refs #65"
```

---

## Task 4: Le panneau de relecture et les états

Issues #66 (moitié client) et #67.

**Files:**
- Create: `resources/js/components/party/audiobook-review.tsx`
- Create: `resources/js/components/party/audiobook-status.tsx`
- Modify: `resources/js/pages/party/audiobook.tsx`

**Interfaces:**
- Consumes: `GreetingState` de la tâche 2, `RecorderTake` de la tâche 3, les routes de la tâche 1.
- Produces: `AudiobookReview({ take, onSubmitted })`, `AudiobookStatus({ greeting, onReopen })`.

**Les quatre blocs et leurs textes.** Titre et corps changent avec l'état, mot pour mot :

| État | Titre | Corps |
| --- | --- | --- |
| `idle` | Enregistrez le message que vos invités entendront | Vos invités décrochent le combiné et entendent votre voix avant de laisser leur propre message. Parlez naturellement, à 20 cm du micro, dans une pièce calme. |
| `recording` | On vous écoute | Parlez normalement. Vous pourrez réécouter et recommencer autant de fois que nécessaire. |
| `ready` | Réécoutez avant d'envoyer | Si le message vous convient, envoyez-le au studio. Nous le mettons en messagerie sur le combiné avant votre événement. |
| `pending` | Message en relecture au studio | Nous vérifions le niveau sonore et la durée. Vous pouvez encore le modifier, cela relancera la validation. |
| `validated` | Votre message est prêt pour le jour J | Le message est en place sur le combiné. Vous pouvez le réécouter ici à tout moment. |
| `rejected` | Votre message est à refaire | Le studio n'a pas pu retenir cet enregistrement. Réenregistrez-le en tenant compte de sa remarque ci-dessous. |

`rejected` n'existe pas dans la maquette : son bloc reprend la forme de `validated` avec le bandeau `À refaire`, le motif du studio en toutes lettres, et un unique bouton `Réenregistrer`.

- [ ] **Step 1: Write the review panel**

Panneau `bg-cream border border-line rounded-[4px]` : sur-titre `Avant d'envoyer`, le texte « Le studio relit votre message puis le met en messagerie sur le combiné. », le champ `Note pour le studio (optionnel)` avec `maxLength={120}` et le placeholder `Prononciation d'un prénom, précision…`, puis le bouton `Envoyer pour validation` et le résumé `Message de M:SS`.

L'envoi poste le blob et la note. Le dépôt du fichier et la soumission sont deux requêtes : la première crée le brouillon, la seconde le remet au studio. Utiliser `router.post` avec `forceFormData: true` pour le fichier, et enchaîner la soumission dans `onSuccess`.

- [ ] **Step 2: Write the status panels**

Un composant, trois rendus. `pending` : sur-titre `En cours de relecture`, la date de soumission formatée en français, boutons `Modifier le message`. `validated` : coche `text-valid`, sur-titre `Validé par le studio`, boutons `Modifier le message` et lien `Télécharger l'enregistrement`. `rejected` : sur-titre `À refaire` en `text-accent`, le motif, bouton `Réenregistrer`.

`Modifier le message` et `Réenregistrer` appellent tous deux `reset()` de l'enregistreur et ramènent la page à `idle` sans requête serveur : c'est le nouveau dépôt qui repassera l'annonce en `draft`.

- [ ] **Step 3: Assemble the page**

La page choisit quoi rendre : l'enregistreur toujours, puis le panneau de relecture si l'enregistreur a un take non soumis, sinon le panneau d'état si une annonce existe.

- [ ] **Step 4: Run the feature tests and build**

```
vendor/bin/sail artisan test --compact tests/Feature/AudioGreetingSubmissionTest.php tests/Feature/AudioGuestbookAccessTest.php
vendor/bin/sail npm run build
```

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat(audio): affiche la relecture et les trois états de l'annonce

Refs #66, #67"
```

---

## Task 5: La modale d'accueil, les repères et la vérification

Issue #64, seconde moitié.

**Files:**
- Create: `resources/js/components/party/audiobook-onboarding.tsx`
- Create: `resources/js/components/party/audiobook-tips.tsx`
- Create: `tests/Web/AudiobookTest.php`
- Modify: `resources/js/pages/party/audiobook.tsx`

- [ ] **Step 1: Write the onboarding modal**

Trois étapes, textes exacts de la maquette :

| # | Titre | Corps |
| --- | --- | --- |
| 01 | Vous enregistrez un message | Une minute au plus, depuis cette page. Réécoutez et recommencez autant de fois que vous voulez. |
| 02 | Le studio le valide | Nous vérifions le son et la durée, puis nous vous confirmons par e-mail sous 48 h. |
| 03 | Vos invités l'entendent | Le jour J, ils décrochent le combiné, écoutent votre voix et laissent leur propre message. |

Titre `Trois étapes, cinq minutes`, sur-titre `Comment ça se passe`, bouton `J'ai compris, on y va`, mention `Un casque ou un téléphone suffit.`

Affichée au premier passage seulement. La mémoire est un `localStorage` sous la clé `boothparty-audiobook-onboarding`, **enveloppé dans try/catch** : en navigation privée l'accès lève, et une modale qui casse la page vaut moins qu'une modale revue deux fois. En dessous de 640 px, feuille par le bas ; au-dessus, boîte centrée.

- [ ] **Step 2: Write the tips**

Sur-titre `Trois repères`, lien `Revoir le déroulement` qui rouvre la modale, et les trois cartes : `Une minute suffit` / « Les messages les plus écoutés durent entre 30 et 60 secondes. », `Dites quoi faire` / « « Laissez-nous un mot après le bip, on l'écoutera au petit-déjeuner. » », `À deux voix` / « Alternez vos deux voix, c'est ce qui fait sourire les invités. »

- [ ] **Step 3: Write the browser test**

`tests/Web/AudiobookTest.php` — le parcours que le navigateur peut couvrir sans micro : la modale s'ouvre au premier passage et pas au second, le lien la rouvre, le compteur d'étapes reflète l'état, le panneau `rejected` affiche le motif du studio.

Utiliser `browserPage()` (`tests/BrowserPage.php`), jamais `visit()` nu pour les clics, et relancer une fois avant d'investiguer un échec : ces tests flakent sur des délais.

- [ ] **Step 4: Full verification**

```
vendor/bin/sail artisan test --compact
vendor/bin/sail npm run build
vendor/bin/sail bin pint --format agent
```

Attendu : la suite complète passe, **à l'exception du seul échec préexistant** `CreatePartyTest > it finds the owner via the interactive search prompt`.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat(audio): ajoute la modale d'accueil et les repères

Refs #64"
```

---

## Ce que ce lot ne fait pas

- Aucune conversion MP3 : `mp3_path` reste nul, la lecture passe par l'original. Lot 4.
- Aucun e-mail : la soumission ne prévient pas le studio. Lot 8.
- Aucune interface de validation côté admin. Lot 5.
- Aucune liste de messages d'invités : la section n'apparaît qu'au lot 7.
