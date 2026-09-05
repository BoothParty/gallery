import { useCallback, useState } from 'react';
import { Head } from '@inertiajs/react';

import usePosterShare, { detectShareSupport, type SharePoster } from '@/hooks/use-poster-share';

interface PostersProps {
    posters: SharePoster[];
}

export default function Posters({ posters }: PostersProps) {
    const [error, setError] = useState<string | null>(null);
    const [hasFallenBack, setFallenBack] = useState(false);
    const onError = useCallback((message: string) => setError(message), []);
    const { status, progress, share } = usePosterShare(onError);

    /**
     * Browsers that cannot hand files to a share sheet get the posters as plain
     * downloads instead.
     */
    const shareOrFallBack = () => {
        if (!detectShareSupport()) {
            setFallenBack(true);

            return;
        }

        share(posters);
    };

    const label =
        status === 'preparing'
            ? `${progress.done}/${progress.total} affiches prêtes`
            : 'Tout partager';

    return (
        <div className="mx-auto flex min-h-screen max-w-md flex-col gap-6 p-6">
            <Head title="Affiches" />

            <header className="space-y-1">
                <h1 className="text-2xl font-semibold">Affiches</h1>
                <p className="text-sm text-gray-500">
                    {posters.length} affiche{posters.length > 1 ? 's' : ''} à imprimer.
                </p>
            </header>

            <ul className="space-y-2">
                {posters.map((poster) => (
                    <li
                        key={poster.id}
                        className="flex items-center justify-between gap-4 rounded-md border border-gray-200 px-4 py-3"
                    >
                        <span className="text-sm">{poster.name}</span>

                        {hasFallenBack && (
                            <a
                                data-poster-link
                                href={poster.url}
                                download={`${poster.name}-affiche.png`}
                                className="text-sm underline"
                            >
                                Télécharger
                            </a>
                        )}
                    </li>
                ))}
            </ul>

            {hasFallenBack ? (
                <p className="text-sm text-gray-500">
                    Ce navigateur ne sait pas partager de fichiers : télécharge les affiches une
                    à une.
                </p>
            ) : (
                <button
                    id="share-posters"
                    type="button"
                    disabled={status !== 'idle'}
                    onClick={shareOrFallBack}
                    className="rounded-md bg-black px-4 py-3 text-white disabled:opacity-60"
                >
                    {label}
                </button>
            )}

            {error && <p className="text-sm text-red-500">{error}</p>}
        </div>
    );
}
