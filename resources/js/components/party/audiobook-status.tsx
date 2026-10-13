import type { GreetingState } from '@/pages/party/audiobook';

interface AudiobookStatusProps {
    greeting: GreetingState;
    /** Sends the client back to a blank recorder, without touching the server. */
    onReopen: () => void;
}

const CHECK_ICON = (
    <svg
        width="18"
        height="18"
        viewBox="0 0 16 16"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinecap="round"
        strokeLinejoin="round"
        className="mt-0.5 shrink-0 text-valid"
        aria-hidden="true"
    >
        <circle cx="8" cy="8" r="6.4" />
        <path d="M5.2 8.2 7.2 10.2l3.6-4.2" />
    </svg>
);

const PANEL = 'flex flex-col gap-4 rounded-[4px] border border-line bg-cream p-5 lg:p-7';
const OUTLINE_BUTTON =
    'cursor-pointer rounded-full border border-ink/20 px-6 py-3.5 text-[15px] text-ink transition-colors hover:border-ink';

/**
 * Where the greeting stands once it has left the client's hands.
 *
 * `rejected` has no counterpart in the design: it borrows the shape of the
 * validated panel and carries the studio's reason verbatim, since that reason
 * is the only thing telling the client what to do differently.
 */
export default function AudiobookStatus({ greeting, onReopen }: AudiobookStatusProps) {
    if (greeting.status === 'pending') {
        const submittedAt = greeting.submitted_at
            ? new Date(greeting.submitted_at).toLocaleString('fr-FR', {
                  day: 'numeric',
                  month: 'long',
                  hour: '2-digit',
                  minute: '2-digit',
              })
            : null;

        return (
            <section className={PANEL} data-greeting-status={greeting.status}>
                <div className="flex flex-col gap-[7px]">
                    <span className="text-[11px] tracking-[0.16em] text-muted uppercase">En cours de relecture</span>
                    <p className="max-w-[54ch] text-sm leading-[1.6] text-pretty text-muted">
                        {submittedAt ? `Message envoyé le ${submittedAt}. ` : ''}
                        Le studio vous confirme la validation par e-mail sous 48 h.
                    </p>
                </div>
                <div>
                    <button type="button" onClick={onReopen} className={OUTLINE_BUTTON}>
                        Modifier le message
                    </button>
                </div>
            </section>
        );
    }

    if (greeting.status === 'validated') {
        return (
            <section className={PANEL} data-greeting-status={greeting.status}>
                <div className="flex items-start gap-3">
                    {CHECK_ICON}
                    <div className="flex flex-col gap-[7px]">
                        <span className="text-[11px] tracking-[0.16em] text-valid uppercase">Validé par le studio</span>
                        <p className="max-w-[54ch] text-sm leading-[1.6] text-pretty text-muted">
                            Votre message sera en messagerie sur le combiné le jour de l'événement. Toute modification
                            demande une nouvelle validation.
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-2.5">
                    <button type="button" onClick={onReopen} className={OUTLINE_BUTTON}>
                        Modifier le message
                    </button>
                    {greeting.url && (
                        <a
                            href={greeting.url}
                            download
                            className="text-[13px] text-muted transition-colors hover:text-ink"
                        >
                            Télécharger l'enregistrement
                        </a>
                    )}
                </div>
            </section>
        );
    }

    if (greeting.status === 'rejected') {
        return (
            <section className={PANEL} data-greeting-status={greeting.status}>
                <div className="flex flex-col gap-[7px]">
                    <span className="text-[11px] tracking-[0.16em] text-accent uppercase">À refaire</span>
                    <p className="max-w-[54ch] text-sm leading-[1.6] text-pretty text-muted">
                        Le studio n'a pas pu retenir cet enregistrement. Réenregistrez-le en tenant compte de sa
                        remarque&nbsp;:
                    </p>
                    {greeting.rejection_reason && (
                        <p className="max-w-[54ch] border-l-2 border-accent pl-3.5 text-sm leading-[1.6] text-pretty text-ink">
                            {greeting.rejection_reason}
                        </p>
                    )}
                </div>
                <div>
                    <button type="button" onClick={onReopen} className={OUTLINE_BUTTON}>
                        Réenregistrer
                    </button>
                </div>
            </section>
        );
    }

    return null;
}
