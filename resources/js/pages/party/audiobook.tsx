import { Head } from '@inertiajs/react';
import { useState } from 'react';

import AudiobookHeader from '@/components/party/audiobook-header';
import AudiobookRecorder from '@/components/party/audiobook-recorder';
import AudiobookReview from '@/components/party/audiobook-review';
import AudiobookStatus from '@/components/party/audiobook-status';
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
    routes: {
        store: string;
        submit: string;
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

type BlockKey = 'idle' | 'recording' | 'ready' | GreetingStatus;

const BLOCK_TITLE: Record<BlockKey, string> = {
    idle: 'Enregistrez le message que vos invités entendront',
    recording: 'On vous écoute',
    ready: "Réécoutez avant d'envoyer",
    draft: "Réécoutez avant d'envoyer",
    pending: 'Message en relecture au studio',
    validated: 'Votre message est prêt pour le jour J',
    rejected: 'Votre message est à refaire',
};

const BLOCK_BODY: Record<BlockKey, string> = {
    idle: 'Vos invités décrochent le combiné et entendent votre voix avant de laisser leur propre message. Parlez naturellement, à 20 cm du micro, dans une pièce calme.',
    recording: 'Parlez normalement. Vous pourrez réécouter et recommencer autant de fois que nécessaire.',
    ready: 'Si le message vous convient, envoyez-le au studio. Nous le mettons en messagerie sur le combiné avant votre événement.',
    draft: 'Si le message vous convient, envoyez-le au studio. Nous le mettons en messagerie sur le combiné avant votre événement.',
    pending: 'Nous vérifions le niveau sonore et la durée. Vous pouvez encore le modifier, cela relancera la validation.',
    validated: 'Le message est en place sur le combiné. Vous pouvez le réécouter ici à tout moment.',
    rejected: "Le studio n'a pas pu retenir cet enregistrement. Réenregistrez-le en tenant compte de sa remarque.",
};

export default function Audiobook({ party, routes, greeting }: AudiobookProps) {
    const recorder = useAudioRecorder({ maxSeconds: MAX_SECONDS });

    /**
     * Set when the client asks to change a greeting the studio already holds.
     * Nothing is written until they record again, so the stored greeting stays
     * intact if they change their mind and reload.
     */
    const [reopened, setReopened] = useState(false);

    const stored = reopened ? null : greeting;

    /**
     * A fresh take always wins over the stored greeting: the client is
     * listening back to what they just recorded, not to what the studio holds.
     */
    const usingFreshTake = recorder.take !== null;
    const playbackUrl = recorder.take?.url ?? stored?.url ?? null;
    const length = recorder.take?.durationSeconds ?? stored?.duration_seconds ?? 0;

    const step = usingFreshTake ? 0 : stored ? STEP_OF[stored.status] : 0;

    let block: BlockKey;

    if (recorder.phase === 'recording') {
        block = 'recording';
    } else if (usingFreshTake) {
        block = 'ready';
    } else if (stored) {
        block = stored.status;
    } else {
        block = 'idle';
    }

    let statusLabel: string;

    if (recorder.phase === 'recording') {
        statusLabel = 'Enregistrement en cours';
    } else if (usingFreshTake) {
        statusLabel = 'À relire';
    } else if (stored) {
        statusLabel = STATUS_LABEL[stored.status];
    } else {
        statusLabel = 'Prêt à enregistrer';
    }

    const reopen = () => {
        setReopened(true);
        recorder.reset();
    };

    return (
        <PartyLayout>
            <Head title={party.title} />
            <AudiobookHeader title={party.title} eventLine={party.event_line} />
            <div className="mx-auto flex w-full max-w-[780px] flex-1 flex-col gap-7 px-5 py-7 sm:px-10 lg:gap-10 lg:py-13">
                <AudiobookSteps current={step} />

                <div className="flex flex-col gap-3.5">
                    <h2 className="font-serif text-[clamp(25px,3.6vw,36px)] leading-[1.12] text-pretty">
                        {BLOCK_TITLE[block]}
                    </h2>
                    <p className="max-w-[56ch] text-[15px] leading-[1.65] text-pretty text-muted">
                        {BLOCK_BODY[block]}
                    </p>
                </div>

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
                        setReopened(true);
                        recorder.reset();
                        void recorder.start();
                    }}
                />

                {recorder.take && (
                    <AudiobookReview
                        blob={recorder.take.blob}
                        durationSeconds={recorder.take.durationSeconds}
                        storeUrl={routes.store}
                        submitUrl={routes.submit}
                        onSubmitted={() => {
                            recorder.reset();
                            setReopened(false);
                        }}
                    />
                )}

                {!recorder.take && stored && stored.status !== 'draft' && (
                    <AudiobookStatus greeting={stored} onReopen={reopen} />
                )}
            </div>
        </PartyLayout>
    );
}
