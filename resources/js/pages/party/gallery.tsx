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
