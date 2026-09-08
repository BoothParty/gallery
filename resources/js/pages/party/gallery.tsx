import { Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react';

import GalleryEditor from '@/components/party/gallery-editor';
import GalleryGrid from '@/components/party/gallery-grid';
import GalleryHero from '@/components/party/gallery-hero';
import GalleryNotice from '@/components/party/gallery-notice';
import GalleryStickyBar from '@/components/party/gallery-sticky-bar';
import GalleryToast from '@/components/party/gallery-toast';
import NewPicturesPill from '@/components/party/new-pictures-pill';
import SelectionBar, { type StackPhoto } from '@/components/party/selection-bar';
import useFlyToStack from '@/hooks/use-fly-to-stack';
import useGalleryChanges from '@/hooks/use-gallery-changes';
import usePhotoSelection from '@/hooks/use-photo-selection';
import usePhotoShare from '@/hooks/use-photo-share';
import usePhotoSwipe from '@/hooks/use-photoswipe';
import PartyLayout from '@/layouts/party-layout';

interface Party {
    id: number;
    name: string;
    display_name: string | null;
    description: string | null;
    banner_url: string | null;
    banner_position_x: number;
    banner_position_y: number;
    banner_zoom: number;
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
    /** Taken off the guests' gallery by the host. Only ever true in a host's own page. */
    hidden: boolean;
    created_at: string;
    updated_at: string;
}

export interface PicturePage {
    data: Picture[];
}

export interface PicturesState {
    count: number;
    last_change: string | null;
}

export interface RefreshConfig {
    /** Seconds between polls while the gallery is empty or just moved. */
    hot: number;
    warm: number;
    cold: number;
    /** Seconds since the last change at which the cadence steps down. */
    warm_after: number;
    cold_after: number;
}

interface Props {
    party: Party;
    pictures: PicturePage;
    picturesCount: number;
    picturesState: PicturesState;
    refresh: RefreshConfig;
    hidePictures: boolean;
    canEdit: boolean;
    maxDownloadPictures: number;
    csrfToken: string;
}

const EXPIRED_HEADING = 'Cette galerie a expiré';

const EMPTY_HEADING = 'La galerie se remplit bientôt';

const EMPTY_MESSAGE =
    'Aucune photo pour le moment. Les clichés apparaissent ici quelques secondes après leur prise, gardez cette page ouverte.';

const EXPIRY_DATE_FORMAT = new Intl.DateTimeFormat('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

/** Reads as « Les photos sont restées en ligne jusqu'au 11 décembre 2026. » */
function expiredMessage(expireAt: string | null): string {
    const until = expireAt === null ? '' : ` jusqu'au ${EXPIRY_DATE_FORMAT.format(new Date(expireAt))}`;

    return `Les photos sont restées en ligne${until}. Vous pouvez demander à l'organisateur de vous les envoyer.`;
}

const TOAST_DURATION = 2200;

const HIDDEN_TOAST = 'Photo masquée pour les invités';

const SHOWN_TOAST = 'Photo remise en ligne';

const VISIBILITY_ERROR_TOAST = "La photo n'a pas pu être mise à jour";

/** Past this many pixels of scroll, a refresh would move the grid under the reader. */
const SILENT_REFRESH_SCROLL_LIMIT = 200;

export default function Gallery({
    party,
    pictures,
    picturesCount,
    picturesState,
    refresh,
    hidePictures,
    canEdit,
    maxDownloadPictures,
    csrfToken,
}: Props) {
    const title = party.display_name || party.name;
    const photos = hidePictures ? [] : pictures.data;
    const isEmpty = photos.length === 0;

    const loadedIds = useMemo(() => photos.map((picture) => picture.id), [photos]);
    const selection = usePhotoSelection(loadedIds);
    const flyToStack = useFlyToStack();

    const lightboxOpen = usePhotoSwipe('#gallery', !selection.selectMode);

    const [editorOpen, setEditorOpen] = useState(false);
    const [toast, setToast] = useState<string | null>(null);
    const toastTimer = useRef<ReturnType<typeof setTimeout>>(undefined);

    const flash = useCallback((message: string) => {
        clearTimeout(toastTimer.current);
        setToast(message);
        toastTimer.current = setTimeout(() => setToast(null), TOAST_DURATION);
    }, []);

    useEffect(() => () => clearTimeout(toastTimer.current), []);

    const photoShare = usePhotoShare(flash);

    /*
     * The host's masking is applied here rather than by reloading the grid: a
     * hidden picture keeps its place in their own gallery, so the only thing
     * that moves is one tile, and reloading would have collapsed the infinite
     * scroll back to its first page under them.
     */
    const [maskEdits, setMaskEdits] = useState<Record<number, boolean>>({});

    const isHidden = useCallback(
        (picture: Picture) => maskEdits[picture.id] ?? picture.hidden,
        [maskEdits],
    );

    const onToggleHidden = useCallback(
        (id: number, hidden: boolean) => {
            setMaskEdits((current) => ({ ...current, [id]: hidden }));

            router.patch(
                `/${encodeURIComponent(party.name)}/pictures/${id}/visibility`,
                { hidden },
                {
                    preserveScroll: true,
                    preserveState: true,
                    only: ['picturesState'],
                    onSuccess: () => flash(hidden ? HIDDEN_TOAST : SHOWN_TOAST),
                    onError: () => {
                        setMaskEdits((current) => ({ ...current, [id]: !hidden }));
                        flash(VISIBILITY_ERROR_TOAST);
                    },
                },
            );
        },
        [flash, party.name],
    );

    const shareMode = photoShare.supported && !hidePictures;

    const changes = useGalleryChanges(party.name, {
        enabled: !hidePictures,
        initial: picturesState,
        config: refresh,
    });

    const { acknowledge } = changes;

    /*
     * `reset` is what keeps the refresh honest: `pictures` is a merged scroll
     * prop, so without it the fresh page 1 would stack on top of what is
     * already loaded and show every picture twice.
     */
    const refreshPictures = useCallback(() => {
        acknowledge();
        router.reload({ only: ['pictures', 'picturesCount'], reset: ['pictures'] });
    }, [acknowledge]);

    /*
     * The refresh only happens on its own when nothing would move under the
     * visitor's fingers. Otherwise the pill takes over and they decide.
     */
    useEffect(() => {
        if (!changes.changed || selection.selectMode || lightboxOpen) {
            return;
        }

        if (window.scrollY > SILENT_REFRESH_SCROLL_LIMIT) {
            return;
        }

        refreshPictures();
    }, [changes.changed, lightboxOpen, refreshPictures, selection.selectMode]);

    const onPillClick = useCallback(() => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        refreshPictures();
    }, [refreshPictures]);

    const onShare = useCallback(() => {
        if (selection.count > maxDownloadPictures) {
            flash(`Sélection trop grande : ${maxDownloadPictures} photos maximum par téléchargement`);

            return;
        }

        photoShare.share(
            selection.selectedIds.map((id) => ({
                id,
                url: `/${encodeURIComponent(party.name)}/pictures/${id}`,
            })),
        );
    }, [flash, maxDownloadPictures, party.name, photoShare, selection.count, selection.selectedIds]);

    const onEnterSelect = useCallback(() => {
        photoShare.refreshSupport();
        selection.enter();
    }, [photoShare, selection]);

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
                .map((picture) => ({
                    id: picture.id,
                    thumbnailUrl: picture.thumbnail_url,
                })),
        [photos, selection.landedIds],
    );

    const onSubmitDownload = useCallback(
        (event: FormEvent<HTMLFormElement>) => {
            if (selection.count > maxDownloadPictures) {
                event.preventDefault();
                flash(`Sélection trop grande : ${maxDownloadPictures} photos maximum par téléchargement`);

                return;
            }

            flash(
                selection.count === 1 ? 'Téléchargement de 1 photo' : `Préparation du ZIP · ${selection.count} photos`,
            );
        },
        [flash, maxDownloadPictures, selection.count],
    );

    return (
        <PartyLayout>
            <Head title={title} />

            <GalleryHero
                title={title}
                subtitle={party.description}
                bannerUrl={party.banner_url}
                position={{
                    x: party.banner_position_x,
                    y: party.banner_position_y,
                }}
                zoom={party.banner_zoom}
                canEdit={canEdit}
                onEdit={() => setEditorOpen(true)}
            />

            {!isEmpty && (
                <GalleryStickyBar
                    count={picturesCount}
                    selectMode={selection.selectMode}
                    allSelected={selection.allSelected}
                    onEnterSelect={onEnterSelect}
                    onExitSelect={selection.exit}
                    onSelectAll={selection.selectAll}
                />
            )}

            <main className="mx-auto w-full max-w-[1440px] grow px-3 pt-4 pb-10 sm:px-8 sm:pt-6 lg:px-12 lg:pt-8 lg:pb-18">
                {hidePictures ? (
                    <GalleryNotice heading={EXPIRED_HEADING}>{expiredMessage(party.expire_at)}</GalleryNotice>
                ) : isEmpty ? (
                    <GalleryNotice heading={EMPTY_HEADING}>{EMPTY_MESSAGE}</GalleryNotice>
                ) : (
                    <GalleryGrid
                        pictures={photos}
                        selectMode={selection.selectMode}
                        isSelected={selection.isSelected}
                        onSelect={onSelect}
                        canEdit={canEdit}
                        isHidden={isHidden}
                        onToggleHidden={onToggleHidden}
                    />
                )}
            </main>

            {changes.changed && <NewPicturesPill delta={changes.delta} onClick={onPillClick} />}

            <SelectionBar
                open={selection.selectMode}
                count={selection.count}
                stack={stack}
                selectedIds={selection.selectedIds}
                action={`/${encodeURIComponent(party.name)}/download`}
                csrfToken={csrfToken}
                mode={shareMode ? 'share' : 'zip'}
                shareStatus={photoShare.status}
                shareProgress={photoShare.progress}
                onShare={onShare}
                onSubmit={onSubmitDownload}
            />

            {canEdit && (
                <GalleryEditor
                    open={editorOpen}
                    onClose={() => setEditorOpen(false)}
                    partyName={party.name}
                    title={party.display_name ?? ''}
                    subtitle={party.description}
                    bannerUrl={party.banner_url}
                    crop={{
                        x: party.banner_position_x,
                        y: party.banner_position_y,
                        zoom: party.banner_zoom,
                    }}
                    onSaved={() => flash('Galerie mise à jour')}
                />
            )}

            <GalleryToast message={toast} raised={selection.selectMode} />
        </PartyLayout>
    );
}
