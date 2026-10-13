import { useCallback, useEffect, useRef, useState } from 'react';

export type RecorderPhase = 'idle' | 'recording' | 'ready';

export interface RecorderTake {
    blob: Blob;
    url: string;
    durationSeconds: number;
}

interface UseAudioRecorderOptions {
    maxSeconds: number;
}

/** How many bars the waveform keeps on screen. */
export const WAVEFORM_BARS = 34;

/** Milliseconds between two level samples; below this the bars only flicker. */
const SAMPLE_INTERVAL = 70;

/**
 * Owns the microphone for the whole life of a take: the stream, the recorder,
 * the analyser feeding the waveform, and the clock that stops at the ceiling.
 * Everything is released together, so a component unmounted mid-recording
 * never leaves the microphone open.
 */
export default function useAudioRecorder({ maxSeconds }: UseAudioRecorderOptions) {
    const [phase, setPhase] = useState<RecorderPhase>('idle');
    const [elapsed, setElapsed] = useState(0);
    const [levels, setLevels] = useState<number[]>([]);
    const [take, setTake] = useState<RecorderTake | null>(null);
    const [error, setError] = useState<string | null>(null);

    const stream = useRef<MediaStream | null>(null);
    const recorder = useRef<MediaRecorder | null>(null);
    const context = useRef<AudioContext | null>(null);
    const analyser = useRef<AnalyserNode | null>(null);
    const frame = useRef<number | null>(null);
    const chunks = useRef<Blob[]>([]);
    const takeUrl = useRef<string | null>(null);

    const release = useCallback(() => {
        if (frame.current !== null) {
            cancelAnimationFrame(frame.current);
            frame.current = null;
        }

        stream.current?.getTracks().forEach((track) => track.stop());
        stream.current = null;

        void context.current?.close();
        context.current = null;
        analyser.current = null;
    }, []);

    useEffect(() => {
        return () => {
            release();

            if (takeUrl.current) {
                URL.revokeObjectURL(takeUrl.current);
            }
        };
    }, [release]);

    const stop = useCallback(() => {
        if (recorder.current && recorder.current.state !== 'inactive') {
            recorder.current.stop();
        }
    }, []);

    const start = useCallback(async () => {
        setError(null);

        let media: MediaStream;

        try {
            media = await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch {
            setError("Nous n'avons pas accès au micro. Autorisez-le dans votre navigateur, puis réessayez.");

            return;
        }

        if (takeUrl.current) {
            URL.revokeObjectURL(takeUrl.current);
            takeUrl.current = null;
        }

        stream.current = media;
        chunks.current = [];
        setTake(null);
        setLevels([]);
        setElapsed(0);
        setPhase('recording');

        context.current = new AudioContext();
        analyser.current = context.current.createAnalyser();
        analyser.current.fftSize = 512;
        context.current.createMediaStreamSource(media).connect(analyser.current);

        const samples = new Uint8Array(analyser.current.frequencyBinCount);
        const startedAt = Date.now();
        let lastSample = 0;

        recorder.current = new MediaRecorder(media);
        recorder.current.ondataavailable = (event) => {
            if (event.data.size > 0) {
                chunks.current.push(event.data);
            }
        };
        recorder.current.onstop = () => {
            const seconds = Math.max(1, (Date.now() - startedAt) / 1000);
            const blob = new Blob(chunks.current, { type: chunks.current[0]?.type ?? 'audio/webm' });
            const url = URL.createObjectURL(blob);

            takeUrl.current = url;
            release();
            setTake({ blob, url, durationSeconds: seconds });
            setElapsed(seconds);
            setPhase('ready');
        };
        recorder.current.start();

        const loop = () => {
            const seconds = (Date.now() - startedAt) / 1000;

            if (seconds >= maxSeconds) {
                stop();

                return;
            }

            if (Date.now() - lastSample > SAMPLE_INTERVAL && analyser.current) {
                lastSample = Date.now();
                analyser.current.getByteTimeDomainData(samples);

                let peak = 0;

                for (const sample of samples) {
                    peak = Math.max(peak, Math.abs(sample - 128) / 128);
                }

                setElapsed(seconds);
                setLevels((previous) => [...previous, Math.min(1, 0.06 + peak * 2.2)].slice(-WAVEFORM_BARS));
            }

            frame.current = requestAnimationFrame(loop);
        };

        frame.current = requestAnimationFrame(loop);
    }, [maxSeconds, release, stop]);

    const reset = useCallback(() => {
        if (takeUrl.current) {
            URL.revokeObjectURL(takeUrl.current);
            takeUrl.current = null;
        }

        setTake(null);
        setLevels([]);
        setElapsed(0);
        setError(null);
        setPhase('idle');
    }, []);

    return { phase, elapsed, levels, take, error, start, stop, reset };
}
