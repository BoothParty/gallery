import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

import formatDuration from '@/lib/duration';

export interface GuestMessage {
    id: number;
    title: string | null;
    display_title: string;
    url: string;
    duration_seconds: number;
    recorded_at: string | null;
    position: number;
}

interface AudiobookMessagesProps {
    messages: GuestMessage[];
    downloadUrl: string;
    csrfToken: string;
    /** Builds the rename endpoint of one message. */
    renameUrl: (message: GuestMessage) => string;
}

/** Bars per row: enough to read as a waveform, few enough to stay legible small. */
const BARS = 28;

/**
 * Stable pseudo-random heights, seeded by the message. The handset gives us no
 * waveform data, and a row of identical bars would read as broken rather than
 * as a recording.
 */
function barsFor(message: GuestMessage): number[] {
    let value = (message.id * 13 + 5) * 9301 + 49297;

    return Array.from({ length: BARS }, () => {
        value = (value * 9301 + 49297) % 233280;

        return 0.2 + (value / 233280) * 0.8;
    });
}

function formatMoment(iso: string | null): string | null {
    if (!iso) {
        return null;
    }

    return new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}

export default function AudiobookMessages({
    messages,
    downloadUrl,
    csrfToken,
    renameUrl,
}: AudiobookMessagesProps) {
    const audio = useRef<HTMLAudioElement | null>(null);
    const [playingId, setPlayingId] = useState<number | null>(null);
    const [position, setPosition] = useState(0);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [draftTitle, setDraftTitle] = useState('');

    useEffect(() => {
        return () => {
            audio.current?.pause();
            audio.current = null;
        };
    }, []);

    const togglePlay = (message: GuestMessage) => {
        if (playingId === message.id) {
            audio.current?.pause();
            setPlayingId(null);

            return;
        }

        audio.current?.pause();
        audio.current = new Audio(message.url);
        audio.current.ontimeupdate = () => setPosition(audio.current?.currentTime ?? 0);
        audio.current.onended = () => {
            setPlayingId(null);
            setPosition(0);
        };

        void audio.current.play();
        setPlayingId(message.id);
        setPosition(0);
    };

    const startRenaming = (message: GuestMessage) => {
        setEditingId(message.id);
        setDraftTitle(message.title ?? '');
    };

    const commitRename = (message: GuestMessage) => {
        setEditingId(null);

        if ((message.title ?? '') === draftTitle) {
            return;
        }

        router.patch(renameUrl(message), { title: draftTitle }, { preserveScroll: true });
    };

    const total = messages.reduce((sum, message) => sum + message.duration_seconds, 0);

    return (
        <section className="flex flex-col gap-6" data-guest-messages>
            <div className="flex flex-wrap items-end justify-between gap-5">
                <div className="flex items-end gap-6 lg:gap-11">
                    <div className="flex flex-col gap-0.5">
                        <span className="font-serif text-[clamp(32px,5vw,46px)] leading-none">{messages.length}</span>
                        <span className="text-[11px] tracking-[0.16em] text-muted uppercase">messages</span>
                    </div>
                    <div className="flex flex-col gap-0.5">
                        <span className="font-serif text-[clamp(32px,5vw,46px)] leading-none">
                            {formatDuration(total)}
                        </span>
                        <span className="text-[11px] tracking-[0.16em] text-muted uppercase">durée totale</span>
                    </div>
                </div>
                <form method="post" action={downloadUrl}>
                    <input type="hidden" name="_token" value={csrfToken} />
                    <button
                        type="submit"
                        className="cursor-pointer rounded-full bg-ink px-6 py-3.5 text-sm text-cream transition-colors hover:bg-ink/85"
                    >
                        Tout télécharger
                    </button>
                </form>
            </div>

            <ul className="flex flex-col border-t border-line">
                {messages.map((message) => {
                    const playing = playingId === message.id;
                    const ratio = playing && message.duration_seconds > 0 ? position / message.duration_seconds : 0;

                    return (
                        <li
                            key={message.id}
                            className="flex items-center gap-3 border-b border-line px-1 py-4 lg:gap-5"
                            data-message={message.id}
                        >
                            <button
                                type="button"
                                onClick={() => togglePlay(message)}
                                aria-label={playing ? 'Pause' : 'Écouter'}
                                className={`flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-full border transition-colors ${
                                    playing ? 'border-ink bg-ink text-cream' : 'border-ink/20 text-ink hover:border-ink'
                                }`}
                            >
                                {playing ? (
                                    <span className="h-3 w-[9px] border-x-[3px] border-current" aria-hidden="true" />
                                ) : (
                                    <span
                                        className="ml-1 h-0 w-0 border-y-[6px] border-l-[9px] border-y-transparent border-l-current"
                                        aria-hidden="true"
                                    />
                                )}
                            </button>

                            <div className="flex min-w-0 flex-1 flex-col gap-2">
                                <div className="flex items-baseline gap-2.5">
                                    {editingId === message.id ? (
                                        <input
                                            autoFocus
                                            value={draftTitle}
                                            maxLength={120}
                                            onChange={(event) => setDraftTitle(event.target.value)}
                                            onBlur={() => commitRename(message)}
                                            onKeyDown={(event) => {
                                                if (event.key === 'Enter') {
                                                    commitRename(message);
                                                }

                                                if (event.key === 'Escape') {
                                                    setEditingId(null);
                                                }
                                            }}
                                            placeholder="Qui parle ?"
                                            className="min-w-0 flex-1 border-b border-ink/30 bg-transparent text-[15px] outline-none"
                                        />
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() => startRenaming(message)}
                                            title="Renommer"
                                            className="min-w-0 cursor-pointer truncate text-left text-[15px] hover:underline"
                                            data-rename={message.id}
                                        >
                                            {message.display_title}
                                        </button>
                                    )}
                                    {formatMoment(message.recorded_at) && (
                                        <span className="shrink-0 text-xs text-subtle">
                                            {formatMoment(message.recorded_at)}
                                        </span>
                                    )}
                                </div>
                                <div className="flex h-6 items-center gap-0.5" aria-hidden="true">
                                    {barsFor(message).map((level, index) => (
                                        <span
                                            key={index}
                                            className={`min-w-px flex-1 rounded-px ${
                                                playing && index / BARS <= ratio ? 'bg-ink' : 'bg-ink/20'
                                            }`}
                                            style={{ height: `${Math.round(14 + level * 86)}%` }}
                                        />
                                    ))}
                                </div>
                            </div>

                            <span className="shrink-0 text-[13px] tabular-nums text-muted">
                                {formatDuration(message.duration_seconds)}
                            </span>
                            <a
                                href={message.url}
                                download
                                aria-label="Télécharger"
                                className="shrink-0 p-1.5 text-subtle transition-colors hover:text-ink"
                            >
                                <svg
                                    width="16"
                                    height="16"
                                    viewBox="0 0 16 16"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="1.5"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                >
                                    <path d="M8 2.6v7.6m0 0L5.2 7.4M8 10.2l2.8-2.8M2.8 12.4h10.4" />
                                </svg>
                            </a>
                        </li>
                    );
                })}
            </ul>

            <p className="max-w-[64ch] text-[13px] leading-[1.7] text-subtle">
                Le fichier groupé contient un MP3 par message, nommé par ordre de passage.
            </p>
        </section>
    );
}
