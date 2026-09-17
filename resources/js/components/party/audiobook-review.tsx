import { router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import formatDuration from '@/lib/duration';

interface AudiobookReviewProps {
    /** The take the client just recorded, awaiting their decision. */
    blob: Blob;
    durationSeconds: number;
    storeUrl: string;
    submitUrl: string;
    /** Called once the studio holds the take, so the page can drop it. */
    onSubmitted: () => void;
}

/**
 * The step between a finished take and the studio.
 *
 * Handing it over is two requests, not one: the first stores the file as a
 * draft, the second passes it to the studio with the client's note. Keeping
 * them apart means an upload that fails halfway leaves a draft the client can
 * retry, rather than a half-submitted greeting the studio can see.
 */
export default function AudiobookReview({
    blob,
    durationSeconds,
    storeUrl,
    submitUrl,
    onSubmitted,
}: AudiobookReviewProps) {
    const [note, setNote] = useState('');
    const [sending, setSending] = useState(false);
    const [failed, setFailed] = useState(false);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setSending(true);
        setFailed(false);

        router.post(
            storeUrl,
            { recording: new File([blob], 'annonce.webm', { type: blob.type }) },
            {
                forceFormData: true,
                preserveScroll: true,
                onError: () => {
                    setSending(false);
                    setFailed(true);
                },
                onSuccess: () => {
                    router.post(
                        submitUrl,
                        { client_note: note },
                        {
                            preserveScroll: true,
                            onError: () => {
                                setSending(false);
                                setFailed(true);
                            },
                            onSuccess: () => {
                                setSending(false);
                                onSubmitted();
                            },
                        },
                    );
                },
            },
        );
    };

    return (
        <form
            onSubmit={submit}
            className="flex flex-col gap-4 rounded-[4px] border border-line bg-cream p-5 lg:gap-[18px] lg:p-7"
        >
            <div className="flex flex-col gap-[7px]">
                <span className="text-[11px] tracking-[0.16em] text-muted uppercase">Avant d'envoyer</span>
                <p className="max-w-[54ch] text-sm leading-[1.6] text-pretty text-muted">
                    Le studio relit votre message puis le met en messagerie sur le combiné. Vous pourrez le modifier
                    jusqu'à 48 h avant l'événement.
                </p>
            </div>

            <label className="flex flex-col gap-2">
                <span className="text-[11px] tracking-[0.16em] text-muted uppercase">
                    Note pour le studio (optionnel)
                </span>
                <input
                    type="text"
                    value={note}
                    onChange={(event) => setNote(event.target.value)}
                    maxLength={120}
                    placeholder="Prononciation d'un prénom, précision…"
                    className="w-full rounded-[3px] border border-ink/15 bg-surface px-3.5 py-3 text-sm text-ink outline-none focus:border-ink"
                />
            </label>

            <div className="flex flex-wrap items-center gap-2.5">
                <button
                    type="submit"
                    disabled={sending}
                    className="cursor-pointer rounded-full bg-ink px-6 py-3.5 text-[15px] text-cream transition-colors hover:bg-ink/85 disabled:cursor-progress disabled:opacity-60"
                >
                    {sending ? 'Envoi en cours…' : 'Envoyer pour validation'}
                </button>
                <span className="text-[13px] text-subtle">Message de {formatDuration(durationSeconds)}</span>
            </div>

            {failed && (
                <p className="text-[13px] text-accent">
                    L'envoi n'a pas abouti. Vérifiez votre connexion et réessayez.
                </p>
            )}
        </form>
    );
}
