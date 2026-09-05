# Refonte de la galerie publique — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Porter la maquette Claude Design sur la galerie publique `{party}/` : thème clair, en-tête d'événement, chargement des photos au scroll, sélection multiple et téléchargement d'un ZIP.

**Architecture:** Le contrôleur passe les photos via `Inertia::scroll()`, le front les consomme avec `<InfiniteScroll>` de `@inertiajs/react`. La page `party/gallery` orchestre l'état de sélection, détenu par un hook, et le distribue à des composants de présentation isolés sous `components/party/`. Le téléchargement est un `POST` classique renvoyant un ZIP streamé depuis S3.

**Tech Stack:** Laravel 12, Inertia v2 (`inertia-laravel` 2.0.22, `@inertiajs/react` 2.3.18), React 19, Tailwind CSS 4, PhotoSwipe 5, Pest 4, `maennchen/zipstream-php` (nouveau).

**Spec :** `docs/superpowers/specs/2026-08-27-galerie-publique-refonte.md`

## Global Constraints

- Toutes les commandes passent par Sail : `vendor/bin/sail artisan …`, `vendor/bin/sail composer …`, `vendor/bin/sail npm …`, `vendor/bin/sail bin pint …`.
- Les composants de la galerie publique doivent vivre sous `resources/js/components/party/` ou `resources/js/hooks/` : `resources/css/app.css` ne scanne que `app.tsx`, `pages/party`, `components/party`, `layouts/party-layout.tsx` et `hooks`. Un composant placé ailleurs perd tous ses styles.
- `resources/css/admin.css` et tout ce qui vit sous `resources/js/pages/admin` ou `resources/js/components/ui` ne doivent pas être modifiés.
- PHP : accolades systématiques, types de retour explicites, promotion de propriétés dans les constructeurs, PHPDoc plutôt que commentaires en ligne.
- Validation en Form Request dédiée, jamais en ligne dans le contrôleur, avec des règles en tableau (convention du projet).
- Pas de `DB::` ni de requête brute : Eloquent uniquement.
- Après toute modification PHP : `vendor/bin/sail bin pint --dirty --format agent`.
- Les tests navigateur de `tests/Web/` flottent sur le timeout de 5 s sans qu'il y ait régression. En cas d'échec, relancer une fois avant d'enquêter.
- Aucune métadonnée d'événement en base dans ce plan : titre, date, lieu et couverture du hero sont des constantes en dur, tout comme les données du pied de page.
- Textes d'interface en français.

---

### Task 1 : Thème clair et coque de page

**Files:**
- Modify: `resources/css/app.css`
- Modify: `resources/js/layouts/party-layout.tsx`
- Modify: `resources/js/components/party/footer.tsx`
- Delete: `resources/js/components/party/navbar.tsx`
- Modify: `resources/js/pages/party/gallery.tsx` (retrait de la prop `partyId` passée au layout)
- Test: `tests/Web/GalleryTest.php` (sections Navigation et Footer)

**Interfaces:**
- Consumes: rien.
- Produits pour les tâches suivantes :
  - tokens Tailwind `surface`, `ink`, `cream`, `muted`, `subtle`, `line`, `placeholder`, utilisables en `bg-surface`, `text-ink`, `border-line`, etc. ;
  - familles `font-sans` (Instrument Sans) et `font-serif` (Instrument Serif) ;
  - classe utilitaire `.animate-shimmer` ;
  - `export default function PartyLayout({ children }: { children: ReactNode })` — plus de prop `partyId` ;
  - `export default function Footer()` — sans props.

- [ ] **Step 1: Mettre à jour les tests navigateur du chrome de page**

Dans `tests/Web/GalleryTest.php`, remplacer intégralement les deux sections `── Navigation ──` et `── Footer ──` (de `it('displays the navigation bar'` jusqu'à la fin du `it('displays the footer'`) par :

```php
// ── Chrome de page ────────────────────────────────────────────────────────────

it('no longer displays the old navigation bar', function () {
    $party = Party::factory()->create();

    $page = visit(route('party.gallery', $party));

    $page->assertDontSee('Accueil')
        ->assertNoJavaScriptErrors();
});

// ── Footer ────────────────────────────────────────────────────────────────────

it('displays the footer call to action', function () {
    $party = Party::factory()->create();

    $page = visit(route('party.gallery', $party));

    $page->assertSee('Une borne photo pour votre événement')
        ->assertSee('Demander un devis')
        ->assertNoJavaScriptErrors();
});

it('displays the footer legal links', function () {
    $party = Party::factory()->create();

    $page = visit(route('party.gallery', $party));

    $page->assertSee('Mentions légales')
        ->assertSee('Confidentialité')
        ->assertSee('SIRET')
        ->assertNoJavaScriptErrors();
});

it('displays the footer social links', function () {
    $party = Party::factory()->create();

    $page = visit(route('party.gallery', $party));

    $page->assertPresent('footer a[aria-label="Instagram"]')
        ->assertPresent('footer a[aria-label="Facebook"]')
        ->assertPresent('footer a[aria-label="LinkedIn"]')
        ->assertNoJavaScriptErrors();
});
```

- [ ] **Step 2: Lancer les tests pour les voir échouer**

```bash
vendor/bin/sail artisan test --compact tests/Web/GalleryTest.php --filter="footer|navigation bar"
```

Attendu : ÉCHEC — « Demander un devis » est absent, « Accueil » est encore présent.

- [ ] **Step 3: Réécrire `resources/css/app.css`**

Remplacer tout le contenu du fichier par :

```css
/**
 * Public party stylesheet: Tailwind plus the party theme only.
 * shadcn lives in admin.css and must not leak into the visitor-facing gallery.
 */

@import url('https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Instrument+Sans:wght@400;500;600&display=swap');

@import 'tailwindcss' source(none);

@import "photoswipe/style.css";

@source '../js/app.tsx';
@source '../js/pages/party';
@source '../js/components/party';
@source '../js/layouts/party-layout.tsx';
@source '../js/hooks';
@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../views';

@theme {
    --font-sans: 'Instrument Sans', Helvetica, Arial, sans-serif;
    --font-serif: 'Instrument Serif', Georgia, serif;

    --color-surface: #f6f5f2;
    --color-ink: #171512;
    --color-cream: #fdfcfa;
    --color-muted: #8a847c;
    --color-subtle: #a8a29a;
    --color-line: rgba(23, 21, 18, 0.09);
    --color-placeholder: #e8e5df;
}

body {
    background: var(--color-surface);
    color: var(--color-ink);
    -webkit-font-smoothing: antialiased;
}

/**
 * PhotoSwipe runs on top of the gallery; its chrome follows the same warm
 * dark tone as the selection bar rather than its default pure black.
 */
.pswp {
    --pswp-bg: #14120f;
    --pswp-placeholder-bg: #26231e;
}

@keyframes shimmer {
    0%,
    100% {
        opacity: 0.45;
    }

    50% {
        opacity: 0.8;
    }
}

.animate-shimmer {
    animation: shimmer 1.4s ease-in-out infinite;
}
```

- [ ] **Step 4: Réécrire le pied de page**

Remplacer tout le contenu de `resources/js/components/party/footer.tsx` par :

```tsx
/**
 * Placeholder values until the brand data gets a proper home. Swapping them for
 * real URLs is a one-line change per entry.
 */
const SOCIAL_LINKS = [
    { label: 'Instagram', href: 'https://www.instagram.com/boothparty/' },
    { label: 'Facebook', href: 'https://www.facebook.com/boothparty/' },
    { label: 'LinkedIn', href: 'https://www.linkedin.com/company/boothparty/' },
] as const;

const LEGAL_LINE = 'BoothParty · SIRET 000 000 000 00000 · © 2026';

const RETENTION_NOTICE =
    "Galerie accessible 90 jours après l'événement, diffusée avec l'accord des organisateurs.";

function InstagramIcon() {
    return (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" aria-hidden="true">
            <rect x="3.5" y="3.5" width="17" height="17" rx="5" />
            <circle cx="12" cy="12" r="4" />
            <circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none" />
        </svg>
    );
}

function FacebookIcon() {
    return (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M13.6 21v-7.6h2.6l.4-3h-3V8.5c0-.9.3-1.5 1.5-1.5h1.6V4.3c-.3 0-1.3-.1-2.4-.1-2.4 0-4 1.4-4 4.1v2.1H8v3h2.3V21h3.3z" />
        </svg>
    );
}

function LinkedInIcon() {
    return (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M5.8 9h2.7v10H5.8V9zm1.35-4.3a1.6 1.6 0 110 3.2 1.6 1.6 0 010-3.2zM10.6 9h2.6v1.4c.45-.8 1.5-1.6 3-1.6 2.2 0 3.6 1.4 3.6 4.1V19h-2.7v-5.4c0-1.3-.5-2.2-1.7-2.2-1.1 0-1.75.75-1.75 2.15V19h-2.7V9z" />
        </svg>
    );
}

const SOCIAL_ICONS = {
    Instagram: InstagramIcon,
    Facebook: FacebookIcon,
    LinkedIn: LinkedInIcon,
} as const;

export default function Footer() {
    return (
        <footer className="border-t border-line">
            <div className="mx-auto flex max-w-[1440px] flex-col gap-7 px-5 pt-9 pb-8 sm:px-10 lg:px-16 lg:pt-14 lg:pb-12">
                <div className="flex flex-wrap items-center justify-between gap-x-8 gap-y-4">
                    <a href="https://boothparty.fr/" target="_blank" rel="noreferrer" className="inline-flex items-center gap-2">
                        <img src="/icon.png" alt="BoothParty" className="h-10 w-10" />
                        <span className="font-serif text-2xl text-ink">BoothParty</span>
                    </a>

                    <div className="flex flex-wrap items-center gap-x-6 gap-y-3 text-sm">
                        <span className="text-muted">Une borne photo pour votre événement&nbsp;?</span>
                        <a
                            href="https://boothparty.fr/"
                            target="_blank"
                            rel="noreferrer"
                            className="rounded-full border border-ink/20 px-5 py-2.5 whitespace-nowrap text-ink transition-colors hover:border-ink"
                        >
                            Demander un devis
                        </a>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-x-6 gap-y-2.5 text-[13px] text-muted">
                    <span className="flex items-center gap-3.5">
                        {SOCIAL_LINKS.map(({ label, href }) => {
                            const Icon = SOCIAL_ICONS[label];

                            return (
                                <a
                                    key={label}
                                    href={href}
                                    target="_blank"
                                    rel="noreferrer"
                                    aria-label={label}
                                    className="flex text-muted transition-colors hover:text-ink"
                                >
                                    <Icon />
                                </a>
                            );
                        })}
                    </span>
                    <span className="text-line">|</span>
                    <a href="https://boothparty.fr/mentions-legales" target="_blank" rel="noreferrer" className="hover:text-ink">
                        Mentions légales
                    </a>
                    <a href="https://boothparty.fr/confidentialite" target="_blank" rel="noreferrer" className="hover:text-ink">
                        Confidentialité
                    </a>
                </div>

                <div className="max-w-[70ch] text-xs leading-relaxed text-subtle">
                    {RETENTION_NOTICE} {LEGAL_LINE}
                </div>
            </div>
        </footer>
    );
}
```

- [ ] **Step 5: Simplifier la mise en page**

Remplacer tout le contenu de `resources/js/layouts/party-layout.tsx` par :

```tsx
import type { ReactNode } from 'react';

import Footer from '@/components/party/footer';

interface PartyLayoutProps {
    children: ReactNode;
}

export default function PartyLayout({ children }: PartyLayoutProps) {
    return (
        <div className="flex min-h-screen flex-col bg-surface font-sans text-ink antialiased">
            {children}
            <Footer />
        </div>
    );
}
```

Dans `resources/js/pages/party/gallery.tsx`, remplacer `<PartyLayout partyId={party.id}>` par `<PartyLayout>`.

- [ ] **Step 6: Supprimer la barre de navigation**

```bash
rm resources/js/components/party/navbar.tsx
```

- [ ] **Step 7: Vérifier qu'aucun import ne subsiste**

```bash
grep -rn "party/navbar\|partyId" resources/js/layouts resources/js/pages/party resources/js/components/party
```

Attendu : aucune sortie.

- [ ] **Step 8: Construire les assets et relancer les tests**

```bash
vendor/bin/sail npm run build
vendor/bin/sail artisan test --compact tests/Web/GalleryTest.php
vendor/bin/sail artisan test --compact tests/Feature/AssetBundlingTest.php
```

Attendu : SUCCÈS pour les nouveaux tests de pied de page et de navigation. Les tests de pagination et de photos de ce fichier restent verts, la Task 2 les reprendra.

- [ ] **Step 9: Commit**

```bash
git add resources/css/app.css resources/js/layouts/party-layout.tsx resources/js/components/party resources/js/pages/party/gallery.tsx tests/Web/GalleryTest.php
git commit -m "feat(gallery): switch the public theme to the light design and rework the footer"
```

---

### Task 2 : Chargement des photos au scroll

**Files:**
- Modify: `app/Http/Controllers/PartyController.php`
- Modify: `resources/js/pages/party/gallery.tsx`
- Delete: `resources/js/components/party/primary-button.tsx`
- Test: `tests/Feature/GalleryTest.php`
- Test: `tests/Web/GalleryTest.php` (section Pagination)

**Interfaces:**
- Consumes: `PartyLayout` sans props de la Task 1.
- Produits pour les tâches suivantes :
  - prop Inertia `pictures` de forme `{ data: Picture[] }`, où `Picture` est `{ id: number; party_id: number; url: string; thumbnail_url: string | null; width: number | null; height: number | null; created_at: string; updated_at: string }` ;
  - `PartyController::PICTURES_PER_PAGE === 24` ;
  - les props `page` et `pagesCount` n'existent plus.

- [ ] **Step 1: Réécrire les tests de props de pagination**

Dans `tests/Feature/GalleryTest.php` :

a. Remplacer les trois assertions qui visent `pictures` comme tableau plat :

```php
it('passes the pictures collection to the gallery page', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('party/gallery')
            ->has('pictures.data', 3)
        );
});

it('passes picture url in the pictures collection', function () {
    $party = Party::factory()->create();
    Picture::factory()->for($party)->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('party/gallery')
            ->has('pictures.data.0.url')
        );
});

it('passes a null thumbnail url when the thumbnail is not generated yet', function () {
    $party = Party::factory()->create();
    Picture::factory()->for($party)->withoutThumbnail()->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pictures.data.0.thumbnail_url', null)
        );
});

it('passes an empty pictures collection when the party has no pictures', function () {
    $party = Party::factory()->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('party/gallery')
            ->has('pictures.data', 0)
        );
});
```

b. Remplacer intégralement la section `── Pagination props ──` (de `it('passes page and pagesCount props'` jusqu'à la fin du fichier) par :

```php
// ── Infinite scroll props ─────────────────────────────────────────────────────

it('paginates pictures at 24 per page', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(25)->for($party)->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pictures.data', PartyController::PICTURES_PER_PAGE)
        );
});

it('returns the correct page when page query param is provided', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(25)->for($party)->create();

    $this->get(route('party.gallery', ['party' => $party, 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pictures.data', 1)
        );
});

it('advertises the pictures prop as a scroll prop', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(25)->for($party)->create();

    $payload = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(route('party.gallery', $party))
        ->assertOk()
        ->json();

    expect($payload['scrollProps']['pictures']['nextPage'])->toBe(2)
        ->and($payload['scrollProps']['pictures']['currentPage'])->toBe(1)
        ->and($payload['mergeProps'])->toContain('pictures.data');
});

it('returns an empty pictures collection for an expired party', function () {
    $party = Party::factory()->expired()->create();
    Picture::factory()->count(3)->for($party)->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pictures.data', 0)
            ->where('hidePictures', true)
        );
});

it('returns pictures in latest-first order', function () {
    $party = Party::factory()->create();
    $oldest = Picture::factory()->for($party)->create(['created_at' => now()->subHour()]);
    $newest = Picture::factory()->for($party)->create(['created_at' => now()]);

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pictures.data.0.id', $newest->id)
            ->where('pictures.data.1.id', $oldest->id)
        );
});
```

Note : `advertises the pictures prop as a scroll prop` passe par une requête Inertia XHR parce que `assertInertia` n'expose que les `props` de l'objet page, pas ses clés `scrollProps` et `mergeProps`.

- [ ] **Step 2: Lancer les tests pour les voir échouer**

```bash
vendor/bin/sail artisan test --compact tests/Feature/GalleryTest.php
```

Attendu : ÉCHEC — `pictures.data` n'existe pas, la prop est encore un tableau plat.

- [ ] **Step 3: Réécrire le contrôleur**

Remplacer tout le contenu de `app/Http/Controllers/PartyController.php` par :

```php
<?php

namespace App\Http\Controllers;

use App\Http\Resources\PartyResource;
use App\Http\Resources\PictureResource;
use App\Models\Party;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class PartyController extends Controller
{
    public const PICTURES_PER_PAGE = 24;

    public function show(Party $party): Response
    {
        $hidePictures = $party->hidePictures();

        return Inertia::render('party/gallery', [
            'party' => PartyResource::make($party)->resolve(),
            'pictures' => Inertia::scroll(
                fn () => PictureResource::collection($this->pictures($party, $hidePictures))
            ),
            'hidePictures' => $hidePictures,
        ]);
    }

    /**
     * An expired party resolves to an empty paginator rather than to a
     * different prop shape, so the page always receives the same structure.
     */
    private function pictures(Party $party, bool $hidePictures): LengthAwarePaginatorContract
    {
        if ($hidePictures) {
            return new LengthAwarePaginator([], 0, self::PICTURES_PER_PAGE);
        }

        return $party->pictures()
            ->latest()
            ->paginate(self::PICTURES_PER_PAGE);
    }
}
```

- [ ] **Step 4: Vérifier que les tests de props passent**

```bash
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail artisan test --compact tests/Feature/GalleryTest.php
```

Attendu : SUCCÈS.

- [ ] **Step 5: Écrire le test navigateur du chargement au scroll**

Dans `tests/Web/GalleryTest.php`, remplacer intégralement la section `── Pagination ──` (de `it('displays pagination controls'` jusqu'à la fin de `it('disables next button on the last page'`) par :

```php
// ── Chargement au scroll ──────────────────────────────────────────────────────

it('no longer displays pagination controls', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(5)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertDontSee('Page suivante')
        ->assertDontSee('Page précédente')
        ->assertNoJavaScriptErrors();
});

it('loads only the first batch of pictures on arrival', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(30)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertCount('#gallery img', 24)
        ->assertNoJavaScriptErrors();
});

it('loads a second batch of pictures when scrolling to the bottom', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(30)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertCount('#gallery img', 24)
        ->script('window.scrollTo(0, document.body.scrollHeight)');

    $page->wait(2)
        ->assertCount('#gallery img', 30)
        ->assertNoJavaScriptErrors();
});
```

- [ ] **Step 6: Lancer le test navigateur pour le voir échouer**

```bash
vendor/bin/sail artisan test --compact tests/Web/GalleryTest.php --filter="scroll|pagination controls|first batch"
```

Attendu : ÉCHEC — la page rend encore les boutons de pagination.

- [ ] **Step 7: Consommer la prop paginée côté page**

Remplacer tout le contenu de `resources/js/pages/party/gallery.tsx` par :

```tsx
import { Head, InfiniteScroll } from '@inertiajs/react';

import PictureCard from '@/components/party/picture-card';
import usePhotoSwipe from '@/hooks/use-photoswipe';
import PartyLayout from '@/layouts/party-layout';

interface Party {
    id: number;
    name: string;
    user_id: number | null;
    expire_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface Picture {
    id: number;
    party_id: number;
    url: string;
    thumbnail_url: string | null;
    width: number | null;
    height: number | null;
    created_at: string;
    updated_at: string;
}

export interface PicturePage {
    data: Picture[];
}

interface Props {
    party: Party;
    pictures: PicturePage;
    hidePictures: boolean;
}

/** Roughly the first row on a desktop viewport — loaded eagerly, the rest lazily. */
const EAGER_PICTURES_COUNT = 6;

export default function Gallery({ party, pictures, hidePictures }: Props) {
    usePhotoSwipe('#gallery');

    return (
        <PartyLayout>
            <Head title={party.name} />

            <section id="photobooth" className="container mx-auto flex grow flex-col items-center pt-28">
                <div className="flex w-3/4 flex-col justify-between lg:flex-row">
                    <div>
                        <h2 className="font-serif text-4xl">PhotoBooth</h2>
                        <p className="text-lg text-muted">Retrouves ici toutes les photos prises avec le photobooth</p>
                    </div>
                </div>

                {hidePictures ? (
                    <div className="mt-6 flex w-5/6 flex-wrap justify-center" id="gallery">
                        <span className="w-3/4 text-xl">
                            Oh non ! Tu es arrivé trop tard, les photos sont désormais cachées. Tu peux toujours
                            demander à l'organisateur de te les envoyer !
                        </span>
                    </div>
                ) : pictures.data.length === 0 ? (
                    <div className="mt-6 flex w-full flex-col items-center justify-center gap-2 py-10" id="gallery">
                        <span className="w-3/4 text-xl">
                            Oh non ! Il n'y a pas encore de photos ici. Rends-toi au photobooth et prends la toute
                            première photo. N'oublie pas de sortir ton meilleur sourire !
                        </span>
                    </div>
                ) : (
                    <InfiniteScroll data="pictures" onlyNext itemsElement="#gallery">
                        <div className="pswp-gallery mt-6 flex w-5/6 flex-wrap justify-center gap-2" id="gallery">
                            {pictures.data.map((picture, index) => (
                                <PictureCard
                                    key={picture.id}
                                    url={picture.url}
                                    thumbnailUrl={picture.thumbnail_url}
                                    id={picture.id}
                                    width={picture.width}
                                    height={picture.height}
                                    priority={index < EAGER_PICTURES_COUNT}
                                />
                            ))}
                        </div>
                    </InfiniteScroll>
                )}
            </section>
        </PartyLayout>
    );
}
```

- [ ] **Step 8: Supprimer le bouton devenu orphelin**

```bash
rm resources/js/components/party/primary-button.tsx
grep -rn "primary-button" resources/js
```

Attendu : aucune sortie du `grep`.

- [ ] **Step 9: Construire et relancer**

```bash
vendor/bin/sail npm run build
vendor/bin/sail artisan test --compact tests/Web/GalleryTest.php
vendor/bin/sail artisan test --compact tests/Feature/GalleryTest.php
```

Attendu : SUCCÈS. Si un test navigateur échoue sur un timeout, le relancer une fois avant d'enquêter.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/PartyController.php resources/js/pages/party/gallery.tsx resources/js/components/party tests/Feature/GalleryTest.php tests/Web/GalleryTest.php
git commit -m "feat(gallery): load pictures on scroll instead of paginating"
```

---

### Task 3 : Hero, barre collante et grille carrée

**Files:**
- Create: `resources/js/components/party/gallery-hero.tsx`
- Create: `resources/js/components/party/gallery-sticky-bar.tsx`
- Create: `resources/js/components/party/gallery-grid.tsx`
- Create: `resources/js/components/party/photo-tile.tsx`
- Modify: `resources/js/pages/party/gallery.tsx`
- Delete: `resources/js/components/party/picture-card.tsx`
- Test: `tests/Web/GalleryTest.php`

**Interfaces:**
- Consumes: `Picture` et `PicturePage` exportés par `pages/party/gallery.tsx` (Task 2), tokens et `.animate-shimmer` (Task 1).
- Produits pour la Task 4 :
  - `PhotoTile` avec les props `{ id: number; url: string; thumbnailUrl: string | null; width: number | null; height: number | null; priority?: boolean; selectMode?: boolean; selected?: boolean }`. Il ne porte aucun gestionnaire de clic : la sélection sera captée au niveau de la grille. Chaque ancre expose `data-id`.
  - `GalleryStickyBar` avec les props `{ count: number; selectable: boolean; selectMode: boolean; allSelected: boolean; onEnterSelect: () => void; onExitSelect: () => void; onSelectAll: () => void }`, câblées sur des fonctions vides à cette étape.
  - `GalleryGrid` avec les props `{ pictures: Picture[]; selectMode: boolean; isSelected: (id: number) => boolean }` ; rend la grille, les skeletons de chargement et le marqueur de fin.

- [ ] **Step 1: Écrire les tests navigateur du nouveau rendu**

Dans `tests/Web/GalleryTest.php`, remplacer la section `── Page rendering ──` et toute la section `── Pictures display ──` par :

```php
// ── Page rendering ────────────────────────────────────────────────────────────

it('renders the gallery hero', function () {
    $party = Party::factory()->create(['name' => 'Mariage de Paul']);

    $page = visit(route('party.gallery', $party));

    $page->assertPresent('[data-gallery-hero]')
        ->assertSee('Camille & Antoine')
        ->assertSee('Domaine des Ormes')
        ->assertNoJavaScriptErrors();
});

it('displays the party name in the page title', function () {
    $party = Party::factory()->create(['name' => 'Soirée Anniversaire']);

    $page = visit(route('party.gallery', $party));

    $page->assertTitleContains('Soirée Anniversaire')
        ->assertNoJavaScriptErrors();
});

it('displays the loaded picture count in the sticky bar', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertSeeIn('[data-gallery-count]', '3')
        ->assertNoJavaScriptErrors();
});

// ── Empty state ───────────────────────────────────────────────────────────────

it('shows empty state message when party has no pictures', function () {
    $party = Party::factory()->create();

    $page = visit(route('party.gallery', $party));

    $page->assertSee("Il n'y a pas encore de photos ici")
        ->assertNoJavaScriptErrors();
});

it('hides the select button when the party has no pictures', function () {
    $party = Party::factory()->create();

    $page = visit(route('party.gallery', $party));

    $page->assertDontSee('Sélectionner')
        ->assertNoJavaScriptErrors();
});

// ── Pictures display ──────────────────────────────────────────────────────────

it('displays pictures when the party has some', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertDontSee("Il n'y a pas encore de photos ici")
        ->assertCount('#gallery img', 3)
        ->assertNoJavaScriptErrors();
});

it('lazy loads pictures below the first row', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(10)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertCount('#gallery img[loading="eager"]', 6)
        ->assertCount('#gallery img[loading="lazy"]', 4)
        ->assertCount('#gallery img[decoding="async"]', 10)
        ->assertNoJavaScriptErrors();
});

it('shows a placeholder instead of the original when the thumbnail is missing', function () {
    $party = Party::factory()->create();
    Picture::factory()->for($party)->withoutThumbnail()->create();

    $page = visit(route('party.gallery', $party));

    $page->assertCount('#gallery [data-picture-placeholder]', 1)
        ->assertCount('#gallery img', 0)
        ->assertNoJavaScriptErrors();
});

it('links each tile to the full resolution picture for the lightbox', function () {
    $party = Party::factory()->create();
    Picture::factory()->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertAttribute('#gallery a', 'data-pswp-width', '1600')
        ->assertAttribute('#gallery a', 'data-pswp-height', '1200')
        ->assertNoJavaScriptErrors();
});

it('renders a tile for a picture stored without dimensions', function () {
    $party = Party::factory()->create();
    Picture::factory()->for($party)->withoutDimensions()->create();

    $page = visit(route('party.gallery', $party));

    $page->assertPresent('#gallery img')
        ->assertNoJavaScriptErrors();
});

it('shows the end of gallery marker once every picture is loaded', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertSee('Fin de la galerie')
        ->assertNoJavaScriptErrors();
});
```

Supprimer le test `reserves the picture height up front to avoid layout shift` : la grille carrée réserve la place par `aspect-ratio` et non plus par les attributs `width` / `height` du ratio natif.

- [ ] **Step 2: Lancer les tests pour les voir échouer**

```bash
vendor/bin/sail artisan test --compact tests/Web/GalleryTest.php --filter="hero|sticky bar|end of gallery"
```

Attendu : ÉCHEC — `[data-gallery-hero]` n'existe pas.

- [ ] **Step 3: Créer le hero**

Créer `resources/js/components/party/gallery-hero.tsx` :

```tsx
/**
 * Hard-coded until the party gains its own title, date, location and cover
 * columns. Wiring real data later means passing these three props from the
 * controller instead of falling back to the defaults.
 */
const DEFAULT_TITLE = 'Camille & Antoine';
const DEFAULT_SUBTITLE = 'Samedi 12 septembre 2026 · Domaine des Ormes';

interface GalleryHeroProps {
    title?: string;
    subtitle?: string;
}

export default function GalleryHero({ title = DEFAULT_TITLE, subtitle = DEFAULT_SUBTITLE }: GalleryHeroProps) {
    return (
        <div
            data-gallery-hero
            className="relative flex h-[62vh] max-h-[560px] min-h-[340px] items-end overflow-hidden bg-placeholder"
            style={{
                backgroundImage:
                    'repeating-linear-gradient(135deg, rgba(23,21,18,.055) 0 2px, rgba(23,21,18,0) 2px 11px)',
            }}
        >
            <span className="absolute top-4 left-5 font-mono text-[11px] tracking-[0.14em] text-ink/40 uppercase sm:top-7 sm:left-16">
                photo de couverture
            </span>

            <div
                className="absolute inset-0"
                style={{
                    background:
                        'linear-gradient(to top, rgba(20,18,15,.62) 0%, rgba(20,18,15,.18) 45%, rgba(20,18,15,0) 75%)',
                }}
            />

            <div className="relative flex w-full flex-col items-center gap-3.5 px-5 pb-7 text-center sm:px-10 lg:px-16 lg:pb-12">
                <h1 className="font-serif text-[clamp(34px,7vw,68px)] leading-none tracking-tight text-cream text-pretty">
                    {title}
                </h1>
                <div className="text-xs tracking-[0.18em] text-cream/80 uppercase">{subtitle}</div>
            </div>
        </div>
    );
}
```

- [ ] **Step 4: Créer la barre collante**

Créer `resources/js/components/party/gallery-sticky-bar.tsx` :

```tsx
interface GalleryStickyBarProps {
    count: number;
    /** Hidden while the gallery holds nothing to select. */
    selectable: boolean;
    selectMode: boolean;
    allSelected: boolean;
    onEnterSelect: () => void;
    onExitSelect: () => void;
    onSelectAll: () => void;
}

export default function GalleryStickyBar({
    count,
    selectable,
    selectMode,
    allSelected,
    onEnterSelect,
    onExitSelect,
    onSelectAll,
}: GalleryStickyBarProps) {
    return (
        <div className="sticky top-0 z-20 border-b border-line bg-surface/90 backdrop-blur-md">
            <div className="mx-auto flex max-w-[1440px] items-center justify-between gap-4 px-4 py-3.5 sm:px-8 lg:px-12">
                <div className="flex min-w-0 items-baseline gap-2">
                    <span data-gallery-count className="font-serif text-xl">
                        {count}
                    </span>
                    <span className="text-xs tracking-[0.14em] text-muted uppercase">photos</span>
                </div>

                {selectable && (
                    <div className="flex items-center gap-2">
                        {selectMode ? (
                            <>
                                <button
                                    type="button"
                                    onClick={onSelectAll}
                                    className="rounded-full border border-ink/20 px-4 py-2 text-[13px] whitespace-nowrap transition-colors hover:border-ink"
                                >
                                    {allSelected ? 'Tout désélectionner' : 'Tout sélectionner'}
                                </button>
                                <button
                                    type="button"
                                    onClick={onExitSelect}
                                    className="rounded-full border border-transparent px-4 py-2 text-[13px] whitespace-nowrap text-muted transition-colors hover:text-ink"
                                >
                                    Annuler
                                </button>
                            </>
                        ) : (
                            <button
                                type="button"
                                onClick={onEnterSelect}
                                className="rounded-full border border-ink/20 px-4 py-2 text-[13px] tracking-wide whitespace-nowrap transition-colors hover:border-ink hover:bg-ink/5"
                            >
                                Sélectionner
                            </button>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
```

- [ ] **Step 5: Créer la vignette**

Créer `resources/js/components/party/photo-tile.tsx` :

```tsx
interface PhotoTileProps {
    id: number;
    url: string;
    /** Null while the thumbnail job has not run yet — a placeholder is shown instead. */
    thumbnailUrl: string | null;
    width: number | null;
    height: number | null;
    /** Load eagerly instead of lazily — reserved for the first, above-the-fold rows. */
    priority?: boolean;
    selectMode?: boolean;
    selected?: boolean;
}

export default function PhotoTile({
    id,
    url,
    thumbnailUrl,
    width,
    height,
    priority = false,
    selectMode = false,
    selected = false,
}: PhotoTileProps) {
    return (
        <a
            href={url}
            data-id={id}
            data-pswp-src={url}
            data-pswp-width={width ?? undefined}
            data-pswp-height={height ?? undefined}
            className="group relative block aspect-square cursor-pointer overflow-hidden rounded-[2px] bg-placeholder"
        >
            {thumbnailUrl ? (
                <img
                    src={thumbnailUrl}
                    alt={`Photo ${id}`}
                    className="block h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.035]"
                    loading={priority ? 'eager' : 'lazy'}
                    decoding="async"
                    fetchPriority={priority ? 'high' : 'low'}
                />
            ) : (
                <div
                    className="h-full w-full animate-shimmer bg-placeholder"
                    role="img"
                    aria-label={`Photo ${id} en cours de préparation`}
                    data-picture-placeholder
                />
            )}

            <span
                className={`pointer-events-none absolute inset-0 transition-all duration-200 ${
                    selected ? 'bg-ink/30 shadow-[inset_0_0_0_2px_var(--color-ink)]' : ''
                }`}
            />

            <span
                className={`pointer-events-none absolute top-2 right-2 h-[22px] w-[22px] items-center justify-center rounded-full border transition-colors duration-150 ${
                    selectMode ? 'flex' : 'hidden'
                } ${selected ? 'border-ink bg-ink' : 'border-cream/75 bg-ink/20'}`}
            >
                {selected && (
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="var(--color-cream)" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                        <path d="M2 6.4 4.6 9 10 3.2" />
                    </svg>
                )}
            </span>
        </a>
    );
}
```

- [ ] **Step 6: Créer la grille**

Créer `resources/js/components/party/gallery-grid.tsx` :

```tsx
import { InfiniteScroll } from '@inertiajs/react';

import PhotoTile from '@/components/party/photo-tile';
import type { Picture } from '@/pages/party/gallery';

/** Roughly the first row on a desktop viewport — loaded eagerly, the rest lazily. */
const EAGER_PICTURES_COUNT = 6;

const GRID_CLASSES =
    'grid gap-1.5 sm:gap-2 lg:gap-3 [grid-template-columns:repeat(auto-fill,minmax(min(46%,210px),1fr))]';

interface GalleryGridProps {
    pictures: Picture[];
    selectMode: boolean;
    isSelected: (id: number) => boolean;
}

function Skeletons() {
    return (
        <div className={`${GRID_CLASSES} mt-1.5 sm:mt-2 lg:mt-3`}>
            {[0, 150, 300, 450].map((delay) => (
                <div
                    key={delay}
                    className="aspect-square animate-shimmer rounded-[2px] bg-placeholder"
                    style={{ animationDelay: `${delay}ms` }}
                />
            ))}
        </div>
    );
}

export default function GalleryGrid({ pictures, selectMode, isSelected }: GalleryGridProps) {
    return (
        <InfiniteScroll
            data="pictures"
            onlyNext
            itemsElement="#gallery"
            loading={<Skeletons />}
            next={({ hasNext, loading }) =>
                hasNext || loading ? null : (
                    <div className="pt-14 pb-2 text-center text-xs tracking-[0.16em] text-subtle uppercase">
                        Fin de la galerie
                    </div>
                )
            }
        >
            <div id="gallery" className={`pswp-gallery ${GRID_CLASSES}`}>
                {pictures.map((picture, index) => (
                    <PhotoTile
                        key={picture.id}
                        id={picture.id}
                        url={picture.url}
                        thumbnailUrl={picture.thumbnail_url}
                        width={picture.width}
                        height={picture.height}
                        priority={index < EAGER_PICTURES_COUNT}
                        selectMode={selectMode}
                        selected={isSelected(picture.id)}
                    />
                ))}
            </div>
        </InfiniteScroll>
    );
}
```

Note sur les slots, vérifiée dans `node_modules/@inertiajs/react/types/InfiniteScroll.d.ts` : le composant expose `loading`, `next` et `previous`, mais **pas** de prop `end`. Le marqueur de fin passe donc par le slot `next`, dont la fonction reçoit un `InfiniteScrollActionSlotProps` portant `{ loading, hasNext, hasPrevious, hasMore, fetch, autoMode, manualMode }`. Fournir ce slot ne désactive pas le chargement automatique — seul `endElement` le ferait ; le test `loads a second batch of pictures when scrolling to the bottom` de la Task 2, relancé à l'étape 9, garde cette garantie.

- [ ] **Step 7: Recomposer la page**

Remplacer tout le contenu de `resources/js/pages/party/gallery.tsx` par :

```tsx
import { Head } from '@inertiajs/react';

import GalleryGrid from '@/components/party/gallery-grid';
import GalleryHero from '@/components/party/gallery-hero';
import GalleryStickyBar from '@/components/party/gallery-sticky-bar';
import usePhotoSwipe from '@/hooks/use-photoswipe';
import PartyLayout from '@/layouts/party-layout';

interface Party {
    id: number;
    name: string;
    user_id: number | null;
    expire_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface Picture {
    id: number;
    party_id: number;
    url: string;
    thumbnail_url: string | null;
    width: number | null;
    height: number | null;
    created_at: string;
    updated_at: string;
}

export interface PicturePage {
    data: Picture[];
}

interface Props {
    party: Party;
    pictures: PicturePage;
    hidePictures: boolean;
}

const EXPIRED_MESSAGE =
    "Oh non ! Tu es arrivé trop tard, les photos sont désormais cachées. Tu peux toujours demander à l'organisateur de te les envoyer !";

const EMPTY_MESSAGE =
    "Oh non ! Il n'y a pas encore de photos ici. Rends-toi au photobooth et prends la toute première photo. N'oublie pas de sortir ton meilleur sourire !";

export default function Gallery({ party, pictures, hidePictures }: Props) {
    usePhotoSwipe('#gallery');

    const photos = hidePictures ? [] : pictures.data;
    const isEmpty = photos.length === 0;

    return (
        <PartyLayout>
            <Head title={party.name} />

            <GalleryHero />

            <GalleryStickyBar
                count={photos.length}
                selectable={!isEmpty}
                selectMode={false}
                allSelected={false}
                onEnterSelect={() => undefined}
                onExitSelect={() => undefined}
                onSelectAll={() => undefined}
            />

            <main className="mx-auto w-full max-w-[1440px] grow px-3 pt-4 pb-10 sm:px-8 sm:pt-6 lg:px-12 lg:pt-8 lg:pb-18">
                {hidePictures ? (
                    <p id="gallery" className="mx-auto max-w-[60ch] py-16 text-center text-xl">
                        {EXPIRED_MESSAGE}
                    </p>
                ) : isEmpty ? (
                    <p id="gallery" className="mx-auto max-w-[60ch] py-16 text-center text-xl">
                        {EMPTY_MESSAGE}
                    </p>
                ) : (
                    <GalleryGrid pictures={photos} selectMode={false} isSelected={() => false} />
                )}
            </main>
        </PartyLayout>
    );
}
```

- [ ] **Step 8: Supprimer l'ancienne carte**

```bash
rm resources/js/components/party/picture-card.tsx
grep -rn "picture-card\|PictureCard" resources/js
```

Attendu : aucune sortie du `grep`.

- [ ] **Step 9: Construire et lancer les tests**

```bash
vendor/bin/sail npm run build
vendor/bin/sail artisan test --compact tests/Web/GalleryTest.php
```

Attendu : SUCCÈS.

- [ ] **Step 10: Commit**

```bash
git add resources/js/components/party resources/js/pages/party/gallery.tsx tests/Web/GalleryTest.php
git commit -m "feat(gallery): add the event hero, sticky bar and square photo grid"
```

---

### Task 4 : Mode sélection

**Files:**
- Create: `resources/js/hooks/use-photo-selection.ts`
- Create: `resources/js/hooks/use-fly-to-stack.ts`
- Create: `resources/js/components/party/selection-bar.tsx`
- Create: `resources/js/components/party/gallery-toast.tsx`
- Modify: `resources/js/hooks/use-photoswipe.ts`
- Modify: `resources/js/components/party/gallery-grid.tsx`
- Modify: `resources/js/pages/party/gallery.tsx`
- Test: `tests/Web/GalleryTest.php`

**Interfaces:**
- Consumes: `PhotoTile` avec `selectMode` / `selected` (Task 3), `GalleryStickyBar` avec ses six props (Task 3).
- Produits pour la Task 5 :
  - `usePhotoSelection(loadedIds: number[]): PhotoSelection` avec
    `interface PhotoSelection { selectMode: boolean; selectedIds: number[]; landedIds: number[]; count: number; allSelected: boolean; isSelected: (id: number) => boolean; enter: () => void; exit: () => void; toggle: (id: number) => void; markLanded: (id: number) => void; selectAll: () => void }` ;
  - `SelectionBar` avec les props `{ open: boolean; count: number; stack: StackPhoto[]; onDownload: () => void }`, où `interface StackPhoto { id: number; thumbnailUrl: string | null }` ; la Task 5 y ajoutera le formulaire ;
  - `GalleryToast` avec les props `{ message: string | null; raised: boolean }` ;
  - `usePhotoSwipe(gallerySelector: string, enabled: boolean)`.

- [ ] **Step 1: Écrire les tests navigateur de la sélection**

Ajouter à la fin de `tests/Web/GalleryTest.php`, avant le test `has no smoke on the gallery page` :

```php
// ── Mode sélection ────────────────────────────────────────────────────────────

it('reveals the selection controls when entering select mode', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->click('Sélectionner')
        ->assertSee('Tout sélectionner')
        ->assertSee('Annuler')
        ->assertPresent('[data-selection-count]')
        ->assertNoJavaScriptErrors();
});

it('counts a picture picked in select mode', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->click('Sélectionner')
        ->click('#gallery a')
        ->assertSeeIn('[data-selection-count]', '1')
        ->assertSee('photo sélectionnée')
        ->assertNoJavaScriptErrors();
});

it('selects every loaded picture at once', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->click('Sélectionner')
        ->click('Tout sélectionner')
        ->assertSeeIn('[data-selection-count]', '3')
        ->assertSee('photos sélectionnées')
        ->assertSee('Tout désélectionner')
        ->assertNoJavaScriptErrors();
});

it('clears the selection when cancelling', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->click('Sélectionner')
        ->click('Tout sélectionner')
        ->click('Annuler')
        ->assertSee('Sélectionner')
        ->assertDontSee('Tout désélectionner')
        ->assertNoJavaScriptErrors();
});

it('does not open the lightbox while in select mode', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->click('Sélectionner')
        ->click('#gallery a')
        ->assertNotPresent('.pswp')
        ->assertNoJavaScriptErrors();
});
```

- [ ] **Step 2: Lancer les tests pour les voir échouer**

```bash
vendor/bin/sail artisan test --compact tests/Web/GalleryTest.php --filter="select mode|selection|lightbox"
```

Attendu : ÉCHEC — cliquer sur « Sélectionner » ne change rien, les boutons étant câblés sur des fonctions vides.

- [ ] **Step 3: Créer le hook de sélection**

Créer `resources/js/hooks/use-photo-selection.ts` :

```ts
import { useCallback, useMemo, useState } from 'react';

export interface PhotoSelection {
    selectMode: boolean;
    selectedIds: number[];
    /** Selected photos whose fly-to-stack animation has finished. */
    landedIds: number[];
    count: number;
    allSelected: boolean;
    isSelected: (id: number) => boolean;
    enter: () => void;
    exit: () => void;
    toggle: (id: number) => void;
    markLanded: (id: number) => void;
    selectAll: () => void;
}

/**
 * Selection state for the gallery. Selection order is kept because the bottom
 * bar shows the last picked thumbnails, and "select all" only ever covers the
 * pictures already loaded on screen.
 */
export default function usePhotoSelection(loadedIds: number[]): PhotoSelection {
    const [selectMode, setSelectMode] = useState(false);
    const [order, setOrder] = useState<number[]>([]);
    const [landed, setLanded] = useState<number[]>([]);

    const reset = useCallback(() => {
        setOrder([]);
        setLanded([]);
    }, []);

    const enter = useCallback(() => setSelectMode(true), []);

    const exit = useCallback(() => {
        setSelectMode(false);
        reset();
    }, [reset]);

    const toggle = useCallback((id: number) => {
        setOrder((current) => (current.includes(id) ? current.filter((x) => x !== id) : [...current, id]));
        setLanded((current) => current.filter((x) => x !== id));
    }, []);

    const markLanded = useCallback((id: number) => {
        setLanded((current) => (current.includes(id) ? current : [...current, id]));
    }, []);

    const allSelected = useMemo(
        () => loadedIds.length > 0 && loadedIds.every((id) => order.includes(id)),
        [loadedIds, order],
    );

    const selectAll = useCallback(() => {
        if (allSelected) {
            reset();

            return;
        }

        setOrder(loadedIds);
        setLanded(loadedIds);
    }, [allSelected, loadedIds, reset]);

    const isSelected = useCallback((id: number) => order.includes(id), [order]);

    return {
        selectMode,
        selectedIds: order,
        landedIds: order.filter((id) => landed.includes(id)),
        count: order.length,
        allSelected,
        isSelected,
        enter,
        exit,
        toggle,
        markLanded,
        selectAll,
    };
}
```

- [ ] **Step 4: Créer l'animation vers la pile**

Créer `resources/js/hooks/use-fly-to-stack.ts` :

```ts
import { useCallback } from 'react';

const STACK_ELEMENT_ID = 'selection-stack';
const THUMBNAIL_SIZE = 46;
const THUMBNAIL_OFFSET = 15;
const MAX_STACK_THUMBNAILS = 4;
const FLIGHT_DURATION = 620;

function prefersReducedMotion(): boolean {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Sends a clone of the tapped thumbnail flying towards the selection stack, then
 * bounces the stack. Resolves once the thumbnail has landed, so the caller can
 * reveal it in the stack at the right moment.
 */
export default function useFlyToStack() {
    return useCallback((anchor: HTMLElement, stackIndex: number): Promise<void> => {
        const stack = document.getElementById(STACK_ELEMENT_ID);
        const image = anchor.querySelector('img');

        if (stack === null || image === null || prefersReducedMotion()) {
            return Promise.resolve();
        }

        const from = image.getBoundingClientRect();
        const to = stack.getBoundingClientRect();
        const targetX = to.left + Math.min(stackIndex, MAX_STACK_THUMBNAILS) * THUMBNAIL_OFFSET + THUMBNAIL_SIZE / 2;
        const targetY = to.top + to.height / 2;

        const clone = document.createElement('div');
        clone.style.cssText = [
            'position:fixed',
            `left:${from.left}px`,
            `top:${from.top}px`,
            `width:${from.width}px`,
            `height:${from.height}px`,
            `background-image:url("${image.getAttribute('src')}")`,
            'background-size:cover',
            'background-position:center',
            'border-radius:2px',
            'z-index:60',
            'pointer-events:none',
            'box-shadow:0 18px 40px rgba(20,18,15,.35)',
            'will-change:transform,opacity',
        ].join(';');
        document.body.appendChild(clone);

        const dx = targetX - (from.left + from.width / 2);
        const dy = targetY - (from.top + from.height / 2);
        const scale = THUMBNAIL_SIZE / from.width;

        const flight = clone.animate(
            [
                { transform: 'translate(0,0) scale(1) rotate(0deg)', opacity: 1, offset: 0 },
                {
                    transform: `translate(${dx * 0.55}px, ${dy * 0.42}px) scale(${(1 + scale) / 2}) rotate(-5deg)`,
                    opacity: 1,
                    offset: 0.55,
                },
                { transform: `translate(${dx}px, ${dy}px) scale(${scale}) rotate(3deg)`, opacity: 0.9, offset: 1 },
            ],
            { duration: FLIGHT_DURATION, easing: 'cubic-bezier(.34,.86,.28,1)' },
        );

        return flight.finished
            .then(() => {
                clone.remove();
                stack.animate([{ transform: 'scale(1)' }, { transform: 'scale(1.11)' }, { transform: 'scale(1)' }], {
                    duration: 260,
                    easing: 'ease-out',
                });
            })
            .catch(() => {
                clone.remove();
            });
    }, []);
}
```

- [ ] **Step 5: Créer la barre de sélection**

Créer `resources/js/components/party/selection-bar.tsx` :

```tsx
export interface StackPhoto {
    id: number;
    thumbnailUrl: string | null;
}

const THUMBNAIL_OFFSET = 15;
const MAX_STACK_THUMBNAILS = 4;

interface SelectionBarProps {
    open: boolean;
    count: number;
    stack: StackPhoto[];
    onDownload: () => void;
}

export default function SelectionBar({ open, count, stack, onDownload }: SelectionBarProps) {
    const shown = stack.slice(-MAX_STACK_THUMBNAILS);
    const stackWidth = shown.length === 0 ? 0 : (shown.length - 1) * THUMBNAIL_OFFSET + 46;

    return (
        <div
            className="fixed inset-x-0 bottom-0 z-30 bg-ink/95 backdrop-blur-md transition-transform duration-300 ease-out"
            style={{ transform: open ? 'translateY(0)' : 'translateY(110%)' }}
            aria-hidden={!open}
        >
            <div className="mx-auto flex max-w-[1440px] items-center justify-between gap-4 px-4 py-3.5 pb-[calc(0.875rem+env(safe-area-inset-bottom))] sm:px-8 lg:px-12">
                <div className="flex min-w-0 items-center gap-3.5">
                    <div
                        id="selection-stack"
                        className="relative h-[46px] flex-none transition-[width] duration-300"
                        style={{ width: `${stackWidth}px` }}
                    >
                        {shown.map((photo, index) => (
                            <div
                                key={photo.id}
                                className="absolute top-0 h-[46px] w-[46px] rounded-[3px] border-[1.5px] border-cream/90 bg-placeholder bg-cover bg-center shadow-lg transition-[left,transform] duration-300"
                                style={{
                                    left: `${index * THUMBNAIL_OFFSET}px`,
                                    zIndex: index + 1,
                                    transform: `rotate(${index % 2 ? 2.5 : -2.5}deg)`,
                                    backgroundImage: photo.thumbnailUrl === null ? undefined : `url("${photo.thumbnailUrl}")`,
                                }}
                            />
                        ))}
                    </div>

                    <div className="min-w-0 text-[13px] leading-tight text-cream/70">
                        <span data-selection-count className="block font-serif text-xl text-cream">
                            {count}
                        </span>
                        {count === 1 ? 'photo sélectionnée' : 'photos sélectionnées'}
                    </div>
                </div>

                <button
                    type="button"
                    onClick={onDownload}
                    disabled={count === 0}
                    className="rounded-full px-5 py-3 text-sm whitespace-nowrap transition-opacity disabled:cursor-default enabled:cursor-pointer enabled:bg-cream enabled:text-ink disabled:bg-cream/20 disabled:text-cream/50"
                >
                    {count > 1 ? 'Télécharger le ZIP' : 'Télécharger'}
                </button>
            </div>
        </div>
    );
}
```

- [ ] **Step 6: Créer le toast**

Créer `resources/js/components/party/gallery-toast.tsx` :

```tsx
interface GalleryToastProps {
    message: string | null;
    /** Lifted above the selection bar while it is open. */
    raised: boolean;
}

export default function GalleryToast({ message, raised }: GalleryToastProps) {
    return (
        <div
            data-gallery-toast
            role="status"
            className="pointer-events-none fixed left-1/2 z-40 rounded-full bg-ink px-5 py-2.5 text-[13px] whitespace-nowrap text-cream transition-all duration-250"
            style={{
                bottom: raised ? '96px' : '28px',
                opacity: message === null ? 0 : 1,
                transform: `translateX(-50%) translateY(${message === null ? '12px' : '0'})`,
            }}
        >
            {message ?? ''}
        </div>
    );
}
```

- [ ] **Step 7: Neutraliser PhotoSwipe en mode sélection**

Dans `resources/js/hooks/use-photoswipe.ts`, changer la signature et la dépendance de l'effet :

```ts
export default function usePhotoSwipe(gallerySelector: string, enabled: boolean = true) {
    useEffect(() => {
        if (!enabled) {
            return;
        }

        const lightbox = new PhotoSwipeLightbox({
```

et la fin de l'effet :

```ts
        return () => {
            lightbox.destroy();
        };
    }, [gallerySelector, enabled]);
}
```

- [ ] **Step 8: Brancher la sélection sur la grille**

Dans `resources/js/components/party/gallery-grid.tsx`, ajouter la prop `onSelect` à l'interface et l'écouteur en phase de capture :

```tsx
import { InfiniteScroll } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

import PhotoTile from '@/components/party/photo-tile';
import type { Picture } from '@/pages/party/gallery';
```

```tsx
interface GalleryGridProps {
    pictures: Picture[];
    selectMode: boolean;
    isSelected: (id: number) => boolean;
    onSelect: (id: number, anchor: HTMLElement) => void;
}
```

et, dans le corps du composant, avant le `return` :

```tsx
    const gridRef = useRef<HTMLDivElement>(null);
    const selectModeRef = useRef(selectMode);
    selectModeRef.current = selectMode;

    /**
     * PhotoSwipe listens on the same anchors. Capturing the click one phase
     * earlier lets select mode swallow it before the lightbox ever sees it.
     */
    useEffect(() => {
        const grid = gridRef.current;

        if (grid === null) {
            return;
        }

        const onClickCapture = (event: MouseEvent) => {
            if (!selectModeRef.current) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            const anchor = (event.target as HTMLElement).closest<HTMLElement>('a[data-id]');

            if (anchor === null) {
                return;
            }

            onSelect(Number(anchor.dataset.id), anchor);
        };

        grid.addEventListener('click', onClickCapture, true);

        return () => grid.removeEventListener('click', onClickCapture, true);
    }, [onSelect]);
```

puis poser la référence sur la grille : `<div id="gallery" ref={gridRef} className={...}>`.

- [ ] **Step 9: Recomposer la page**

Dans `resources/js/pages/party/gallery.tsx`, remplacer les imports et le corps du composant `Gallery` (le reste du fichier — interfaces et constantes de messages — est inchangé) :

```tsx
import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import GalleryGrid from '@/components/party/gallery-grid';
import GalleryHero from '@/components/party/gallery-hero';
import GalleryStickyBar from '@/components/party/gallery-sticky-bar';
import GalleryToast from '@/components/party/gallery-toast';
import SelectionBar, { type StackPhoto } from '@/components/party/selection-bar';
import useFlyToStack from '@/hooks/use-fly-to-stack';
import usePhotoSelection from '@/hooks/use-photo-selection';
import usePhotoSwipe from '@/hooks/use-photoswipe';
import PartyLayout from '@/layouts/party-layout';
```

```tsx
const TOAST_DURATION = 2200;

export default function Gallery({ party, pictures, hidePictures }: Props) {
    const photos = hidePictures ? [] : pictures.data;
    const isEmpty = photos.length === 0;

    const loadedIds = useMemo(() => photos.map((picture) => picture.id), [photos]);
    const selection = usePhotoSelection(loadedIds);
    const flyToStack = useFlyToStack();

    usePhotoSwipe('#gallery', !selection.selectMode);

    const [toast, setToast] = useState<string | null>(null);
    const toastTimer = useRef<ReturnType<typeof setTimeout>>(undefined);

    const flash = useCallback((message: string) => {
        clearTimeout(toastTimer.current);
        setToast(message);
        toastTimer.current = setTimeout(() => setToast(null), TOAST_DURATION);
    }, []);

    useEffect(() => () => clearTimeout(toastTimer.current), []);

    const onSelect = useCallback(
        (id: number, anchor: HTMLElement) => {
            const wasSelected = selection.isSelected(id);
            selection.toggle(id);

            if (!wasSelected) {
                void flyToStack(anchor, selection.landedIds.length).then(() => selection.markLanded(id));
            }
        },
        [flyToStack, selection],
    );

    const stack: StackPhoto[] = useMemo(
        () =>
            selection.landedIds
                .map((id) => photos.find((picture) => picture.id === id))
                .filter((picture): picture is NonNullable<typeof picture> => picture !== undefined)
                .map((picture) => ({ id: picture.id, thumbnailUrl: picture.thumbnail_url })),
        [photos, selection.landedIds],
    );

    const onDownload = useCallback(() => {
        flash(
            selection.count === 1
                ? 'Téléchargement de 1 photo'
                : `Préparation du ZIP · ${selection.count} photos`,
        );
    }, [flash, selection.count]);

    return (
        <PartyLayout>
            <Head title={party.name} />

            <GalleryHero />

            <GalleryStickyBar
                count={photos.length}
                selectable={!isEmpty}
                selectMode={selection.selectMode}
                allSelected={selection.allSelected}
                onEnterSelect={selection.enter}
                onExitSelect={selection.exit}
                onSelectAll={selection.selectAll}
            />

            <main className="mx-auto w-full max-w-[1440px] grow px-3 pt-4 pb-10 sm:px-8 sm:pt-6 lg:px-12 lg:pt-8 lg:pb-18">
                {hidePictures ? (
                    <p id="gallery" className="mx-auto max-w-[60ch] py-16 text-center text-xl">
                        {EXPIRED_MESSAGE}
                    </p>
                ) : isEmpty ? (
                    <p id="gallery" className="mx-auto max-w-[60ch] py-16 text-center text-xl">
                        {EMPTY_MESSAGE}
                    </p>
                ) : (
                    <GalleryGrid
                        pictures={photos}
                        selectMode={selection.selectMode}
                        isSelected={selection.isSelected}
                        onSelect={onSelect}
                    />
                )}
            </main>

            <SelectionBar open={selection.selectMode} count={selection.count} stack={stack} onDownload={onDownload} />

            <GalleryToast message={toast} raised={selection.selectMode} />
        </PartyLayout>
    );
}
```

- [ ] **Step 10: Construire et lancer les tests**

```bash
vendor/bin/sail npm run build
vendor/bin/sail artisan test --compact tests/Web/GalleryTest.php
```

Attendu : SUCCÈS.

- [ ] **Step 11: Commit**

```bash
git add resources/js tests/Web/GalleryTest.php
git commit -m "feat(gallery): add the picture selection mode with its bottom bar"
```

---

### Task 5 : Téléchargement d'un ZIP de la sélection

**Files:**
- Modify: `composer.json` (via `sail composer require`)
- Modify: `config/party.php`
- Modify: `routes/web.php`
- Create: `app/Http/Controllers/PictureDownloadController.php`
- Create: `app/Http/Requests/DownloadPicturesRequest.php`
- Modify: `app/Http/Controllers/PartyController.php`
- Modify: `resources/js/components/party/selection-bar.tsx`
- Modify: `resources/js/pages/party/gallery.tsx`
- Test: `tests/Feature/PictureDownloadTest.php`
- Test: `tests/Web/GalleryTest.php`

**Interfaces:**
- Consumes: `SelectionBar` et `usePhotoSelection` de la Task 4, `PartyController::show` de la Task 2.
- Produits : route nommée `party.download` en `POST /{party}/download`, prop Inertia `maxDownloadPictures: number`, prop `csrfToken: string`.

- [ ] **Step 1: Installer la dépendance**

```bash
vendor/bin/sail composer require maennchen/zipstream-php
```

- [ ] **Step 2: Écrire les tests de la route de téléchargement**

Créer `tests/Feature/PictureDownloadTest.php` :

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

/**
 * Puts real bytes behind each picture so the ZIP stream has something to read.
 *
 * @param  \Illuminate\Support\Collection<int, Picture>  $pictures
 */
function storePictureFiles($pictures): void
{
    $pictures->each(function (Picture $picture): void {
        Storage::disk('s3')->put($picture->path, 'fake-jpeg-bytes');
    });
}

// ── Happy path ────────────────────────────────────────────────────────────────

it('streams a zip of the selected pictures', function () {
    $party = Party::factory()->create(['name' => 'mariage-paul']);
    $pictures = Picture::factory()->count(3)->for($party)->create();
    storePictureFiles($pictures);

    $response = $this->post(route('party.download', $party), [
        'ids' => $pictures->pluck('id')->all(),
    ]);

    $response->assertOk()
        ->assertHeader('content-type', 'application/zip');

    expect($response->headers->get('content-disposition'))->toContain('mariage-paul-photos.zip');
});

it('writes one entry per selected picture', function () {
    $party = Party::factory()->create();
    $pictures = Picture::factory()->count(2)->for($party)->create();
    storePictureFiles($pictures);

    $response = $this->post(route('party.download', $party), [
        'ids' => $pictures->pluck('id')->all(),
    ]);

    $archive = $response->streamedContent();

    expect(substr_count($archive, 'photo-'))->toBeGreaterThanOrEqual(2);
});

it('downloads a single selected picture as a zip too', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();
    storePictureFiles(collect([$picture]));

    $this->post(route('party.download', $party), ['ids' => [$picture->id]])
        ->assertOk()
        ->assertHeader('content-type', 'application/zip');
});

// ── Scoping ───────────────────────────────────────────────────────────────────

it('rejects a picture belonging to another party', function () {
    $party = Party::factory()->create();
    $otherParty = Party::factory()->create();
    $foreign = Picture::factory()->for($otherParty)->create();

    $this->post(route('party.download', $party), ['ids' => [$foreign->id]])
        ->assertSessionHasErrors('ids.0');
});

it('rejects an unknown picture id', function () {
    $party = Party::factory()->create();

    $this->post(route('party.download', $party), ['ids' => [999999]])
        ->assertSessionHasErrors('ids.0');
});

// ── Guard rails ───────────────────────────────────────────────────────────────

it('rejects an empty selection', function () {
    $party = Party::factory()->create();

    $this->post(route('party.download', $party), ['ids' => []])
        ->assertSessionHasErrors('ids');
});

it('rejects a selection above the configured cap', function () {
    config()->set('party.download.max_pictures', 2);

    $party = Party::factory()->create();
    $pictures = Picture::factory()->count(3)->for($party)->create();
    storePictureFiles($pictures);

    $this->post(route('party.download', $party), ['ids' => $pictures->pluck('id')->all()])
        ->assertSessionHasErrors('ids');
});

it('refuses to download an expired party', function () {
    $party = Party::factory()->expired()->create();
    $pictures = Picture::factory()->count(2)->for($party)->create();
    storePictureFiles($pictures);

    $this->post(route('party.download', $party), ['ids' => $pictures->pluck('id')->all()])
        ->assertForbidden();
});

it('throttles repeated download requests', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();
    storePictureFiles(collect([$picture]));

    foreach (range(1, 10) as $ignored) {
        $this->post(route('party.download', $party), ['ids' => [$picture->id]])->assertOk();
    }

    $this->post(route('party.download', $party), ['ids' => [$picture->id]])
        ->assertStatus(429);
});
```

- [ ] **Step 3: Lancer les tests pour les voir échouer**

```bash
vendor/bin/sail artisan test --compact tests/Feature/PictureDownloadTest.php
```

Attendu : ÉCHEC — la route `party.download` n'existe pas.

- [ ] **Step 4: Ajouter la configuration**

Dans `config/party.php`, insérer avant le `];` final :

```php
    /*
    |--------------------------------------------------------------------------
    | Selection Download
    |--------------------------------------------------------------------------
    |
    | Ceiling on how many pictures a visitor may bundle into a single ZIP. The
    | archive is streamed rather than buffered, so the limit exists to protect
    | the S3 egress and the visitor's own connection, not the server memory.
    |
    */

    'download' => [
        'max_pictures' => (int) env('PARTY_DOWNLOAD_MAX_PICTURES', 300),
    ],

```

- [ ] **Step 5: Créer la Form Request**

Créer `app/Http/Requests/DownloadPicturesRequest.php` :

```php
<?php

namespace App\Http\Requests;

use App\Models\Party;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DownloadPicturesRequest extends FormRequest
{
    /**
     * A visitor may download from any gallery that is still published; an
     * expired one hides its pictures and must not hand them out either.
     */
    public function authorize(): bool
    {
        /** @var Party $party */
        $party = $this->route('party');

        return ! $party->hidePictures();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Party $party */
        $party = $this->route('party');

        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.config('party.download.max_pictures')],
            'ids.*' => [
                'required',
                'integer',
                Rule::exists('pictures', 'id')->where('party_id', $party->id),
            ],
        ];
    }
}
```

- [ ] **Step 6: Créer le contrôleur**

Créer `app/Http/Controllers/PictureDownloadController.php` :

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\DownloadPicturesRequest;
use App\Models\Party;
use App\Models\Picture;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\ZipStream;

class PictureDownloadController extends Controller
{
    /**
     * Stream the selected pictures as a ZIP archive.
     *
     * The archive is built on the fly from the S3 originals so neither a
     * temporary file nor the whole selection ever lands in memory, whatever
     * the size of the selection.
     */
    public function __invoke(DownloadPicturesRequest $request, Party $party): StreamedResponse
    {
        /** @var list<int> $ids */
        $ids = $request->validated('ids');

        $pictures = $party->pictures()
            ->whereIn('id', $ids)
            ->latest()
            ->get();

        $filename = Str::slug($party->name).'-photos.zip';

        return response()->stream(
            function () use ($pictures, $filename): void {
                /** @var FilesystemAdapter $disk */
                $disk = Storage::disk('s3');

                $zip = new ZipStream(outputName: $filename, sendHttpHeaders: false);

                $pictures->values()->each(function (Picture $picture, int $index) use ($zip, $disk): void {
                    $stream = $disk->readStream($picture->path);

                    if ($stream === null) {
                        return;
                    }

                    $zip->addFileFromStream('photo-'.($index + 1).'.jpg', $stream);
                });

                $zip->finish();
            },
            200,
            [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }
}
```

- [ ] **Step 7: Déclarer la route**

Dans `routes/web.php`, ajouter dans le groupe `Route::prefix('{party}')->name('party.')`, juste après la ligne de la galerie :

```php
    Route::post('download', PictureDownloadController::class)
        ->middleware('throttle:10,1')
        ->name('download');
```

et l'import correspondant en haut du fichier :

```php
use App\Http\Controllers\PictureDownloadController;
```

- [ ] **Step 8: Vérifier que les tests serveur passent**

```bash
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail artisan test --compact tests/Feature/PictureDownloadTest.php
```

Attendu : SUCCÈS. Si `assertHeader('content-type', 'application/zip')` échoue à cause d'un `charset` ajouté par Symfony, remplacer l'assertion par
`expect($response->headers->get('content-type'))->toStartWith('application/zip')`.

- [ ] **Step 9: Exposer le plafond et le jeton à la page**

Dans `app/Http/Controllers/PartyController.php`, ajouter deux props dans le tableau de `Inertia::render` :

```php
            'hidePictures' => $hidePictures,
            'maxDownloadPictures' => (int) config('party.download.max_pictures'),
            'csrfToken' => csrf_token(),
```

- [ ] **Step 10: Écrire le test de la prop de plafond**

Ajouter à la fin de `tests/Feature/GalleryTest.php` :

```php
// ── Download props ────────────────────────────────────────────────────────────

it('passes the download cap to the gallery page', function () {
    config()->set('party.download.max_pictures', 42);

    $party = Party::factory()->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('maxDownloadPictures', 42)
            ->has('csrfToken')
        );
});
```

Lancer :

```bash
vendor/bin/sail artisan test --compact tests/Feature/GalleryTest.php --filter="download cap"
```

Attendu : SUCCÈS (le contrôleur est déjà modifié à l'étape précédente).

- [ ] **Step 11: Écrire le test navigateur du formulaire**

Ajouter à `tests/Web/GalleryTest.php`, dans la section `── Mode sélection ──` :

```php
it('posts the selection to the download route', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->click('Sélectionner')
        ->click('Tout sélectionner')
        ->assertAttribute('[data-download-form]', 'method', 'post')
        ->assertAttributeContains('[data-download-form]', 'action', '/download')
        ->assertCount('[data-download-form] input[name="ids[]"]', 3)
        ->assertNoJavaScriptErrors();
});

it('refuses to submit a selection above the cap', function () {
    config()->set('party.download.max_pictures', 2);

    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->click('Sélectionner')
        ->click('Tout sélectionner')
        ->click('Télécharger le ZIP')
        ->assertSeeIn('[data-gallery-toast]', 'maximum')
        ->assertNoJavaScriptErrors();
});
```

Lancer :

```bash
vendor/bin/sail artisan test --compact tests/Web/GalleryTest.php --filter="download route|above the cap"
```

Attendu : ÉCHEC — `[data-download-form]` n'existe pas.

- [ ] **Step 12: Transformer la barre en formulaire**

Dans `resources/js/components/party/selection-bar.tsx`, ajouter l'import de type en tête de fichier :

```tsx
import type { FormEvent } from 'react';
```

puis remplacer l'interface et les deux éléments concernés :

```tsx
interface SelectionBarProps {
    open: boolean;
    count: number;
    stack: StackPhoto[];
    selectedIds: number[];
    action: string;
    csrfToken: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}
```

Remplacer la signature du composant par :

```tsx
export default function SelectionBar({ open, count, stack, selectedIds, action, csrfToken, onSubmit }: SelectionBarProps) {
```

Remplacer la `<div>` intérieure par un `<form>` :

```tsx
            <form
                data-download-form
                method="post"
                action={action}
                onSubmit={onSubmit}
                className="mx-auto flex max-w-[1440px] items-center justify-between gap-4 px-4 py-3.5 pb-[calc(0.875rem+env(safe-area-inset-bottom))] sm:px-8 lg:px-12"
            >
                <input type="hidden" name="_token" value={csrfToken} />
                {selectedIds.map((id) => (
                    <input key={id} type="hidden" name="ids[]" value={id} />
                ))}
```

Fermer par `</form>` au lieu de `</div>`, et remplacer le bouton par :

```tsx
                <button
                    type="submit"
                    disabled={count === 0}
                    className="rounded-full px-5 py-3 text-sm whitespace-nowrap transition-opacity disabled:cursor-default enabled:cursor-pointer enabled:bg-cream enabled:text-ink disabled:bg-cream/20 disabled:text-cream/50"
                >
                    {count > 1 ? 'Télécharger le ZIP' : 'Télécharger'}
                </button>
```

- [ ] **Step 13: Brancher le formulaire dans la page**

Dans `resources/js/pages/party/gallery.tsx` :

a. Ajouter les deux props à l'interface `Props` :

```tsx
interface Props {
    party: Party;
    pictures: PicturePage;
    hidePictures: boolean;
    maxDownloadPictures: number;
    csrfToken: string;
}
```

b. Étendre la déstructuration : `export default function Gallery({ party, pictures, hidePictures, maxDownloadPictures, csrfToken }: Props) {`

c. Remplacer `onDownload` par un gestionnaire de soumission, en ajoutant `FormEvent` à l'import de `react` (`import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react';`) :

```tsx
    const onSubmitDownload = useCallback(
        (event: FormEvent<HTMLFormElement>) => {
            if (selection.count > maxDownloadPictures) {
                event.preventDefault();
                flash(`Sélection trop grande : ${maxDownloadPictures} photos maximum par téléchargement`);

                return;
            }

            flash(
                selection.count === 1
                    ? 'Téléchargement de 1 photo'
                    : `Préparation du ZIP · ${selection.count} photos`,
            );
        },
        [flash, maxDownloadPictures, selection.count],
    );
```

d. Remplacer l'usage de `SelectionBar` :

```tsx
            <SelectionBar
                open={selection.selectMode}
                count={selection.count}
                stack={stack}
                selectedIds={selection.selectedIds}
                action={`/${encodeURIComponent(party.name)}/download`}
                csrfToken={csrfToken}
                onSubmit={onSubmitDownload}
            />
```

- [ ] **Step 14: Construire et lancer la suite complète**

```bash
vendor/bin/sail npm run build
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail artisan test --compact
```

Attendu : SUCCÈS sur l'ensemble. Relancer une fois les échecs de `tests/Web/` qui ressemblent à des timeouts.

- [ ] **Step 15: Commit**

```bash
git add composer.json composer.lock config/party.php routes/web.php app/Http resources/js tests/Feature/PictureDownloadTest.php tests/Feature/GalleryTest.php tests/Web/GalleryTest.php
git commit -m "feat(gallery): stream the selected pictures as a zip archive"
```

---

## Points de vigilance

- **`<InfiniteScroll>` et le mode sélection.** L'arrivée d'un nouveau lot ne doit pas vider la sélection : `usePhotoSelection` garde des identifiants, jamais des index, et `loadedIds` ne sert qu'à `selectAll` et `allSelected`. Vérifier à la main qu'après avoir sélectionné une photo puis chargé un lot, le compteur ne bouge pas.
- **Synchronisation d'URL.** `<InfiniteScroll>` réécrit `?page=` dans l'URL au fil du défilement par défaut. Combiné à `onlyNext` (chargement arrière désactivé), un visiteur qui recharge la page à mi-galerie ne recevrait que le lot atteint, rendant les lots précédents définitivement inatteignables. Décision du propriétaire : `<InfiniteScroll>` porte donc `preserveUrl`, l'URL publique reste `/{party}` en toutes circonstances, et `onlyNext` est conservé.
- **`Str::slug` sur le nom de party.** Le nom est déjà un sous-domaine, donc déjà proche d'un slug ; `Str::slug` protège l'en-tête `Content-Disposition` d'un nom exotique en base.
- **`X-Accel-Buffering: no`** est présent pour empêcher un reverse proxy de bufferiser l'archive entière avant de la transmettre.
- **Throttle et tests.** `phpunit.xml` fixe `CACHE_STORE=array`, donc le compteur du middleware `throttle` repart de zéro à chaque test : `throttles repeated download requests` n'empoisonne pas les autres tests du fichier et n'a pas besoin de nettoyage explicite.
- **`enabled` de `usePhotoSwipe`.** Basculer en mode sélection détruit la lightbox et la recrée en sortant. C'est voulu : PhotoSwipe relit le DOM à chaque ouverture, il n'y a donc rien à préserver entre deux cycles.
