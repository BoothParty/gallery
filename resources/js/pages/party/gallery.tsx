import { useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import { IconArrowLeft, IconArrowRight } from '@tabler/icons-react';

import PictureCard from '@/components/party/picture-card';
import PrimaryButton from '@/components/party/primary-button';
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

interface Picture {
    id: number;
    party_id: number;
    url: string;
    thumbnail_url: string | null;
    width: number | null;
    height: number | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    party: Party;
    pictures: Picture[];
    page: number;
    pagesCount: number;
    hidePictures: boolean;
}

/** Roughly the first row on a desktop viewport — loaded eagerly, the rest lazily. */
const EAGER_PICTURES_COUNT = 6;

/**
 * Paginating only changes the pictures; the party and its expiry never do.
 * Requesting just those props keeps the payload small and lets Inertia reuse
 * a prefetched response, which requires identical visit options.
 */
const PAGE_VISIT_OPTIONS = {
    only: ['pictures', 'page', 'pagesCount'],
    preserveScroll: false,
};

export default function Gallery({ party, pictures, page, pagesCount, hidePictures }: Props) {
    usePhotoSwipe('#gallery');

    const pageUrl = (targetPage: number) => `/${encodeURIComponent(party.name)}?page=${targetPage}`;

    const goToPage = (targetPage: number) => {
        router.visit(pageUrl(targetPage), PAGE_VISIT_OPTIONS);
    };

    /**
     * Warm the adjacent pages so paginating feels instant. Inertia serves the
     * next visit straight from its prefetch cache when the options match.
     */
    useEffect(() => {
        [page - 1, page + 1]
            .filter((target) => target >= 1 && target <= pagesCount && target !== page)
            .forEach((target) => {
                router.prefetch(pageUrl(target), { method: 'get', ...PAGE_VISIT_OPTIONS }, { cacheFor: '2m' });
            });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page, pagesCount, party.name]);

    return (
        <PartyLayout partyId={party.id}>
            <Head title={party.name} />

            <section id="photobooth" className="container mx-auto flex grow flex-col items-center justify-between pt-28">
                <div className="flex w-3/4 flex-col justify-between lg:flex-row">
                    <div>
                        <h2 className="font-syne text-4xl font-bold text-white">PhotoBooth</h2>
                        <p className="text-lg text-gray-400">Retrouves ici toutes les photos prises avec le photobooth</p>
                    </div>
                </div>

                <div className="flex w-full grow flex-col items-center justify-between">
                    {hidePictures ? (
                        <div className="mt-6 flex w-5/6 flex-wrap justify-center" id="gallery">
                            <span className="w-3/4 text-xl text-gray-200">
                                Oh non ! Tu es arrivé trop tard, les photos sont désormais cachées. Tu peux toujours
                                demander à l'organisateur de te les envoyer !
                            </span>
                        </div>
                    ) : (
                        <div
                            className="pswp-gallery pswp-gallery--single-column mt-6 flex w-5/6 flex-wrap justify-center gap-2"
                            id="gallery"
                        >
                            {pictures.length === 0 ? (
                                <div className="flex w-full flex-col items-center justify-center gap-2 py-10">
                                    <span className="w-3/4 text-xl text-gray-200">
                                        Oh non ! Il n'y a pas encore de photos ici. Rends-toi au photobooth et prends la
                                        toute première photo. N'oublie pas de sortir ton meilleur sourire !
                                    </span>
                                </div>
                            ) : (
                                pictures.map((picture, index) => (
                                    <PictureCard
                                        key={picture.id}
                                        url={picture.url}
                                        thumbnailUrl={picture.thumbnail_url}
                                        id={picture.id}
                                        width={picture.width}
                                        height={picture.height}
                                        priority={index < EAGER_PICTURES_COUNT}
                                    />
                                ))
                            )}
                        </div>
                    )}

                    {!hidePictures && (
                        <div className="mt-8 flex w-3/4 flex-row items-center justify-around gap-2">
                            <PrimaryButton
                                text="Page précédente"
                                icon={<IconArrowLeft className="h-6 w-6" />}
                                disabled={page === 1}
                                onClick={() => goToPage(page - 1)}
                                className="gap-0 text-[0] sm:text-base lg:gap-2 lg:text-base"
                                paddings="py-1.5 px-4"
                            />
                            <span className="rounded-full bg-primary px-4 py-1.5 font-medium text-white">
                                {page} / {pagesCount} pages
                            </span>
                            <PrimaryButton
                                text="Page suivante"
                                icon={<IconArrowRight className="h-6 w-6" />}
                                disabled={page === pagesCount}
                                onClick={() => goToPage(page + 1)}
                                className="flex-row-reverse gap-0 text-[0] sm:text-base lg:gap-2 lg:text-base"
                                paddings="py-1.5 px-4"
                            />
                        </div>
                    )}
                </div>
            </section>
        </PartyLayout>
    );
}
