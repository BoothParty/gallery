import { useEffect, useRef, useState } from 'react';

import formatDuration from '@/lib/duration';
import { WAVEFORM_BARS, type RecorderPhase } from '@/hooks/use-audio-recorder';

interface AudiobookRecorderProps {
    phase: RecorderPhase;
    /** Seconds elapsed while recording, then the length of the take. */
    elapsed: number;
    /** Live levels, newest last, at most WAVEFORM_BARS of them. */
    levels: number[];
    /** Anything playable: the fresh take, or the greeting already stored. */
    playbackUrl: string | null;
    statusLabel: string;
    /** Shown under the buttons when the microphone could not be opened. */
    error: string | null;
    canRerecord: boolean;
    onStart: () => void;
    onStop: () => void;
    onRerecord: () => void;
}

/**
 * Pseudo-random but stable heights for a take that has no live levels — a
 * greeting reloaded from the server has no waveform of its own, and a row of
 * identical bars would read as broken rather than as silent.
 */
function restingLevels(seed: number): number[] {
    let value = seed * 9301 + 49297;

    return Array.from({ length: WAVEFORM_BARS }, () => {
        value = (value * 9301 + 49297) % 233280;

        return 0.18 + (value / 233280) * 0.82;
    });
}

const PLAY_ICON = (
    <span className="h-0 w-0 border-y-[7px] border-l-[10px] border-y-transparent border-l-ink" aria-hidden="true" />
);

const PAUSE_ICON = (
    <span className="h-3 w-[9px] border-x-[3px] border-ink" aria-hidden="true" />
);

const LIGHT_BUTTON =
    'flex cursor-pointer items-center gap-2.5 rounded-full bg-cream px-7 py-4 text-[15px] text-ink transition-colors hover:bg-cream/85';

/**
 * The dark card the whole page turns around: what the microphone is doing, the
 * waveform, the running time and the two or three buttons that apply right
 * now. It decides nothing about the greeting's fate — it only records, plays
 * back, and reports.
 */
export default function AudiobookRecorder({
    phase,
    elapsed,
    levels,
    playbackUrl,
    statusLabel,
    error,
    canRerecord,
    onStart,
    onStop,
    onRerecord,
}: AudiobookRecorderProps) {
    const audio = useRef<HTMLAudioElement | null>(null);
    const [playing, setPlaying] = useState(false);
    const [position, setPosition] = useState(0);

    useEffect(() => {
        audio.current?.pause();
        audio.current = null;
        setPlaying(false);
        setPosition(0);
    }, [playbackUrl]);

    useEffect(() => {
        return () => {
            audio.current?.pause();
            audio.current = null;
        };
    }, []);

    const togglePlay = () => {
        if (!playbackUrl) {
            return;
        }

        if (playing) {
            audio.current?.pause();
            setPlaying(false);

            return;
        }

        audio.current ??= new Audio(playbackUrl);
        audio.current.ontimeupdate = () => setPosition(audio.current?.currentTime ?? 0);
        audio.current.onended = () => {
            setPlaying(false);
            setPosition(0);
        };

        void audio.current.play();
        setPlaying(true);
    };

    const recording = phase === 'recording';
    const hasTake = phase === 'ready' || playbackUrl !== null;

    let bars: number[];

    if (recording) {
        bars = [...Array.from({ length: Math.max(0, WAVEFORM_BARS - levels.length) }, () => 0.05), ...levels];
    } else if (hasTake) {
        bars = restingLevels(Math.round(elapsed * 37) + 11);
    } else {
        bars = Array.from({ length: WAVEFORM_BARS }, () => 0.14);
    }

    const played = hasTake && elapsed > 0 ? position / elapsed : 0;

    const timeLabel = recording
        ? formatDuration(elapsed)
        : hasTake && position > 0
          ? `${formatDuration(position)} / ${formatDuration(elapsed)}`
          : hasTake
            ? formatDuration(elapsed)
            : '0:00';

    const timeHint = recording ? 'sur 1:30 max' : hasTake ? 'durée du message' : '30 à 60 s conseillées';

    return (
        <section className="flex flex-col items-center gap-5 rounded-[4px] bg-ink px-5 py-7 text-cream sm:px-10 lg:gap-7 lg:py-11">
            <p className="flex items-center gap-2.5 text-[11px] tracking-[0.18em] text-cream/60 uppercase">
                <span
                    className={`h-[7px] w-[7px] rounded-full ${
                        recording ? 'animate-pulse bg-accent' : 'bg-cream/40'
                    }`}
                    aria-hidden="true"
                />
                {statusLabel}
            </p>

            <div className="flex h-16 w-full items-center justify-center gap-[3px] lg:h-24" aria-hidden="true">
                {bars.map((level, index) => (
                    <span
                        key={index}
                        className={`max-w-2 min-w-0.5 flex-1 rounded-sm transition-[height] duration-100 ${
                            recording
                                ? 'bg-accent'
                                : hasTake && index / WAVEFORM_BARS <= (played || 1)
                                  ? 'bg-cream'
                                  : 'bg-cream/20'
                        }`}
                        style={{ height: `${Math.round(8 + level * 92)}%` }}
                    />
                ))}
            </div>

            <p className="flex items-baseline gap-2.5">
                <span className="font-serif text-[clamp(34px,6vw,52px)] leading-none tracking-[-0.01em]">
                    {timeLabel}
                </span>
                <span className="text-xs tracking-[0.12em] text-cream/45 uppercase">{timeHint}</span>
            </p>

            <div className="flex flex-wrap items-center justify-center gap-2.5">
                {phase === 'idle' && !playbackUrl && (
                    <button type="button" onClick={onStart} className={LIGHT_BUTTON}>
                        <span className="h-[11px] w-[11px] rounded-full bg-accent" aria-hidden="true" />
                        Commencer l'enregistrement
                    </button>
                )}

                {recording && (
                    <button
                        type="button"
                        onClick={onStop}
                        className="flex cursor-pointer items-center gap-2.5 rounded-full bg-accent px-7 py-4 text-[15px] text-cream transition-colors hover:bg-accent/85"
                    >
                        <span className="h-2.5 w-2.5 rounded-[1px] bg-cream" aria-hidden="true" />
                        Arrêter
                    </button>
                )}

                {!recording && playbackUrl && (
                    <button type="button" onClick={togglePlay} className={LIGHT_BUTTON}>
                        {playing ? PAUSE_ICON : PLAY_ICON}
                        {playing ? 'Pause' : phase === 'ready' ? 'Réécouter' : 'Écouter le message'}
                    </button>
                )}

                {!recording && canRerecord && (
                    <button
                        type="button"
                        onClick={onRerecord}
                        className="cursor-pointer rounded-full border border-cream/30 px-6 py-4 text-[15px] text-cream transition-colors hover:border-cream"
                    >
                        Réenregistrer
                    </button>
                )}
            </div>

            {error && <p className="text-center text-xs text-cream/60">{error}</p>}
        </section>
    );
}
