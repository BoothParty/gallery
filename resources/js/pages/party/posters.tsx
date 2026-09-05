import { Head } from '@inertiajs/react';

interface Poster {
    id: number;
    name: string;
    url: string;
}

interface PostersProps {
    posters: Poster[];
}

export default function Posters({ posters }: PostersProps) {
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
                    <li key={poster.id} className="rounded-md border border-gray-200 px-4 py-3">
                        <span className="text-sm">{poster.name}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
