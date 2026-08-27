import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react';

import GalleryGrid from '@/components/party/gallery-grid';
import GalleryHero from '@/components/party/gallery-hero';
import GalleryStickyBar from '@/components/party/gallery-sticky-bar';
import GalleryToast from '@/components/party/gallery-toast';
import SelectionBar, { type StackPhoto } from '@/components/party/selection-bar';
import useFlyToStack from '@/hooks/use-fly-to-stack';
import usePhotoSelection from '@/hooks/use-photo-selection';
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
    picturesCount: number;
    hidePictures: boolean;
    maxDownloadPictures: number;
    csrfToken: string;
}

const EXPIRED_MESSAGE =
    "Oh non ! Tu es arrivé trop tard, les photos sont désormais cachées. Tu peux toujours demander à l'organisateur de te les envoyer !";

const EMPTY_MESSAGE =
    "Oh non ! Il n'y a pas encore de photos ici. Rends-toi au photobooth et prends la toute première photo. N'oublie pas de sortir ton meilleur sourire !";

const TOAST_DURATION = 2200;

export default function Gallery({ party, pictures, picturesCount, hidePictures, maxDownloadPictures, csrfToken }: Props) {
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

    return (
        <PartyLayout>
            <Head title={party.name} />

            <GalleryHero />

            <GalleryStickyBar
                count={picturesCount}
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

            <SelectionBar
                open={selection.selectMode}
                count={selection.count}
                stack={stack}
                selectedIds={selection.selectedIds}
                action={`/${encodeURIComponent(party.name)}/download`}
                csrfToken={csrfToken}
                onSubmit={onSubmitDownload}
            />

            <GalleryToast message={toast} raised={selection.selectMode} />
        </PartyLayout>
    );
}
