const STEPS = ['Enregistrer', 'Validation studio', 'En messagerie le jour J'];

interface AudiobookStepsProps {
    /** 0 while recording or reviewing, 1 once submitted, 2 once validated. */
    current: number;
}

/**
 * Where the client stands in the three moves the greeting goes through. Only
 * the steps already reached are inked; the rest stay pale.
 */
export default function AudiobookSteps({ current }: AudiobookStepsProps) {
    return (
        <ol className="grid grid-cols-3 gap-2.5">
            {STEPS.map((step, index) => (
                <li key={step} className="flex flex-col gap-2.5">
                    <span
                        className={`h-0.5 rounded-sm ${index <= current ? 'bg-ink' : 'bg-ink/15'}`}
                        aria-hidden="true"
                    />
                    <span
                        className={`text-[11px] leading-[1.4] tracking-[0.1em] uppercase ${
                            index <= current ? 'text-ink' : 'text-subtle'
                        }`}
                    >
                        {step}
                    </span>
                </li>
            ))}
        </ol>
    );
}
