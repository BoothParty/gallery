import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react';

import GalleryEditor from '@/components/party/gallery-editor';
import GalleryGrid from '@/components/party/gallery-grid';
import GalleryHero from '@/components/party/gallery-hero';
import GalleryNotice from '@/components/party/gallery-notice';
import GalleryStickyBar from '@/components/party/gallery-sticky-bar';
import GalleryToast from '@/components/party/gallery-toast';
import SelectionBar, { type StackPhoto } from '@/components/party/selection-bar';
import useFlyToStack from '@/hooks/use-fly-to-stack';
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
    created_at: string;
    updated_at: string;
}

export interface PicturePage {
    data: Picture[];
}

interface Props {
    party: Party;
    pictures: PicturePage;
    picturesCount: number;
    hidePictures: boolean;
    canEdit: boolean;
    maxDownloadPictures: number;
    csrfToken: string;
}

const EXPIRED_HEADING = 'Cette galerie a expiré';

const EMPTY_HEADING = 'La galerie se remplit bientôt';

const EMPTY_MESSAGE =
    'Aucune photo pour le moment. Les clichés apparaissent ici quelques instants après leur prise, rafraîchissez la page pour les découvrir.';

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

export default function Gallery({
    party,
    pictures,
    picturesCount,
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

    usePhotoSwipe('#gallery', !selection.selectMode);

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

    const shareMode = photoShare.supported && !hidePictures;

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
                    />
                )}
            </main>

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
