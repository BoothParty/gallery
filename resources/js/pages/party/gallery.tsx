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
