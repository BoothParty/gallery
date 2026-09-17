import { Head } from '@inertiajs/react';

import AudiobookHeader from '@/components/party/audiobook-header';
import AudiobookRecorder from '@/components/party/audiobook-recorder';
import AudiobookSteps from '@/components/party/audiobook-steps';
import useAudioRecorder from '@/hooks/use-audio-recorder';
import PartyLayout from '@/layouts/party-layout';

export type GreetingStatus = 'draft' | 'pending' | 'validated' | 'rejected';

export interface GreetingState {
    status: GreetingStatus;
    url: string | null;
    duration_seconds: number | null;
    client_note: string | null;
    rejection_reason: string | null;
    submitted_at: string | null;
}

interface AudiobookProps {
    party: {
        title: string;
        event_line: string | null;
    };
    greeting: GreetingState | null;
}

const STEP_OF: Record<GreetingStatus, number> = {
    draft: 0,
    rejected: 0,
    pending: 1,
    validated: 2,
};

const STATUS_LABEL: Record<GreetingStatus, string> = {
    draft: 'À relire',
    pending: 'En attente de validation',
    validated: 'Validé',
    rejected: 'À refaire',
};

/** Ceiling on a greeting, in seconds. The recorder stops on its own there. */
const MAX_SECONDS = 90;

export default function Audiobook({ party, greeting }: AudiobookProps) {
    const recorder = useAudioRecorder({ maxSeconds: MAX_SECONDS });

    /**
     * A fresh take always wins over the stored greeting: the client is
     * listening back to what they just recorded, not to what the studio holds.
     */
    const usingFreshTake = recorder.take !== null;
    const playbackUrl = recorder.take?.url ?? greeting?.url ?? null;
    const length = recorder.take?.durationSeconds ?? greeting?.duration_seconds ?? 0;

    const step = usingFreshTake ? 0 : greeting ? STEP_OF[greeting.status] : 0;

    let statusLabel: string;

    if (recorder.phase === 'recording') {
        statusLabel = 'Enregistrement en cours';
    } else if (usingFreshTake) {
        statusLabel = 'À relire';
    } else if (greeting) {
        statusLabel = STATUS_LABEL[greeting.status];
    } else {
        statusLabel = 'Prêt à enregistrer';
    }

    return (
        <PartyLayout>
            <Head title={party.title} />
            <AudiobookHeader title={party.title} eventLine={party.event_line} />
            <div className="mx-auto flex w-full max-w-[780px] flex-1 flex-col gap-7 px-5 py-7 sm:px-10 lg:gap-10 lg:py-13">
                <AudiobookSteps current={step} />
                <AudiobookRecorder
                    phase={recorder.phase}
                    elapsed={recorder.phase === 'recording' ? recorder.elapsed : length}
                    levels={recorder.levels}
                    playbackUrl={recorder.phase === 'recording' ? null : playbackUrl}
                    statusLabel={statusLabel}
                    error={recorder.error}
                    canRerecord={recorder.phase !== 'recording' && playbackUrl !== null}
                    onStart={() => void recorder.start()}
                    onStop={recorder.stop}
                    onRerecord={() => {
                        recorder.reset();
                        void recorder.start();
                    }}
                />
            </div>
        </PartyLayout>
    );
}
