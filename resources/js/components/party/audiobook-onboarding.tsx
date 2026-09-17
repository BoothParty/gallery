import { useEffect, useState } from 'react';

const STORAGE_KEY = 'boothparty-audiobook-onboarding';

const STEPS = [
    {
        number: '01',
        title: 'Vous enregistrez un message',
        body: 'Une minute au plus, depuis cette page. Réécoutez et recommencez autant de fois que vous voulez.',
    },
    {
        number: '02',
        title: 'Le studio le valide',
        body: 'Nous vérifions le son et la durée, puis nous vous confirmons par e-mail sous 48 h.',
    },
    {
        number: '03',
        title: "Vos invités l'entendent",
        body: 'Le jour J, ils décrochent le combiné, écoutent votre voix et laissent leur propre message.',
    },
];

/**
 * Whether this browser has already been walked through the three steps.
 *
 * Every access is guarded: a private window throws on `localStorage`, and a
 * client who cannot open the page at all is worse off than one who sees the
 * explanation twice.
 */
function hasSeenOnboarding(): boolean {
    try {
        return window.localStorage.getItem(STORAGE_KEY) === 'seen';
    } catch {
        return false;
    }
}

function rememberOnboarding(): void {
    try {
        window.localStorage.setItem(STORAGE_KEY, 'seen');
    } catch {
        // Nothing to do: the modal simply shows again next time.
    }
}

interface AudiobookOnboardingProps {
    /** Forced open by the « Revoir le déroulement » link. */
    open: boolean;
    onOpen: () => void;
    onClose: () => void;
}

export default function AudiobookOnboarding({ open, onOpen, onClose }: AudiobookOnboardingProps) {
    const [ready, setReady] = useState(false);

    useEffect(() => {
        if (!hasSeenOnboarding()) {
            onOpen();
        }

        setReady(true);
        // Runs once: reopening later is the link's job, not this effect's.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const dismiss = () => {
        rememberOnboarding();
        onClose();
    };

    if (!ready || !open) {
        return null;
    }

    return (
        <>
            <div className="fixed inset-0 z-50 bg-shade/40 backdrop-blur-[2px]" onClick={dismiss} aria-hidden="true" />
            <div
                role="dialog"
                aria-modal="true"
                aria-label="Comment ça se passe"
                data-onboarding
                className="fixed bottom-0 left-0 z-51 flex max-h-[88vh] w-full flex-col gap-5 overflow-y-auto rounded-t-[14px] bg-surface p-6 pb-[calc(1.5rem+env(safe-area-inset-bottom))] shadow-[0_-12px_40px_rgba(20,18,15,0.22)] sm:top-1/2 sm:bottom-auto sm:left-1/2 sm:max-h-[86vh] sm:w-[min(620px,calc(100vw-48px))] sm:-translate-x-1/2 sm:-translate-y-1/2 sm:gap-6 sm:rounded-[5px] sm:p-8 sm:shadow-[0_24px_70px_rgba(20,18,15,0.3)] lg:p-11"
            >
                <div className="flex flex-col gap-3">
                    <span className="text-[11px] tracking-[0.16em] text-muted uppercase">Comment ça se passe</span>
                    <h2 className="font-serif text-[clamp(26px,4vw,38px)] leading-[1.1] text-pretty">
                        Trois étapes, cinq minutes
                    </h2>
                </div>

                <ol className="flex flex-col border-b border-line">
                    {STEPS.map((step) => (
                        <li key={step.number} className="flex gap-4 border-t border-line py-5 lg:gap-6">
                            <span className="shrink-0 pt-1 font-mono text-xs tracking-[0.1em] text-subtle">
                                {step.number}
                            </span>
                            <div className="flex flex-col gap-1.5">
                                <span className="font-serif text-[clamp(19px,2.6vw,23px)] leading-[1.2]">
                                    {step.title}
                                </span>
                                <span className="max-w-[52ch] text-sm leading-[1.6] text-pretty text-muted">
                                    {step.body}
                                </span>
                            </div>
                        </li>
                    ))}
                </ol>

                <div className="flex flex-wrap items-center gap-3.5">
                    <button
                        type="button"
                        onClick={dismiss}
                        data-onboarding-dismiss
                        className="cursor-pointer rounded-full bg-ink px-7 py-4 text-[15px] text-cream transition-colors hover:bg-ink/85"
                    >
                        J'ai compris, on y va
                    </button>
                    <span className="text-[13px] text-subtle">Un casque ou un téléphone suffit.</span>
                </div>
            </div>
        </>
    );
}
