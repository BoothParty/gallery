import { Head } from '@inertiajs/react';

import AudiobookHeader from '@/components/party/audiobook-header';
import AudiobookSteps from '@/components/party/audiobook-steps';
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

export default function Audiobook({ party, greeting }: AudiobookProps) {
    const step = greeting ? STEP_OF[greeting.status] : 0;

    return (
        <PartyLayout>
            <Head title={party.title} />
            <AudiobookHeader title={party.title} eventLine={party.event_line} />
            <div className="mx-auto flex w-full max-w-[780px] flex-1 flex-col gap-7 px-5 py-7 sm:px-10 lg:gap-10 lg:py-13">
                <AudiobookSteps current={step} />
            </div>
        </PartyLayout>
    );
}
