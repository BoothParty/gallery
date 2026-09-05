/**
 * Placeholder values until the brand data gets a proper home. Swapping them for
 * real URLs is a one-line change per entry.
 */
const SOCIAL_LINKS = [
    { label: 'Instagram', href: 'https://www.instagram.com/boothparty/' },
    { label: 'Facebook', href: 'https://www.facebook.com/boothparty/' },
    { label: 'LinkedIn', href: 'https://www.linkedin.com/company/boothparty/' },
] as const;

function InstagramIcon() {
    return (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" aria-hidden="true">
            <rect x="3.5" y="3.5" width="17" height="17" rx="5" />
            <circle cx="12" cy="12" r="4" />
            <circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none" />
        </svg>
    );
}

function FacebookIcon() {
    return (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M13.6 21v-7.6h2.6l.4-3h-3V8.5c0-.9.3-1.5 1.5-1.5h1.6V4.3c-.3 0-1.3-.1-2.4-.1-2.4 0-4 1.4-4 4.1v2.1H8v3h2.3V21h3.3z" />
        </svg>
    );
}

function LinkedInIcon() {
    return (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M5.8 9h2.7v10H5.8V9zm1.35-4.3a1.6 1.6 0 110 3.2 1.6 1.6 0 010-3.2zM10.6 9h2.6v1.4c.45-.8 1.5-1.6 3-1.6 2.2 0 3.6 1.4 3.6 4.1V19h-2.7v-5.4c0-1.3-.5-2.2-1.7-2.2-1.1 0-1.75.75-1.75 2.15V19h-2.7V9z" />
        </svg>
    );
}

const SOCIAL_ICONS = {
    Instagram: InstagramIcon,
    Facebook: FacebookIcon,
    LinkedIn: LinkedInIcon,
} as const;

export default function Footer() {
    return (
        <footer className="border-t border-line">
            <div className="mx-auto flex max-w-[1440px] flex-col gap-7 px-5 pt-9 pb-8 sm:px-10 lg:px-16 lg:pt-14 lg:pb-12">
                <div className="flex flex-wrap items-center justify-between gap-x-8 gap-y-4">
                    <a href="https://boothparty.fr/" target="_blank" rel="noreferrer" className="inline-flex items-center">
                        <img
                            src="/logo.svg"
                            alt="BoothParty"
                            width="233"
                            height="87"
                            className="h-10 w-auto opacity-90 transition-opacity hover:opacity-100"
                        />
                    </a>

                    <div className="flex flex-wrap items-center gap-x-6 gap-y-3 text-sm md:order-1">
                        <a
                            href="https://boothparty.fr/"
                            target="_blank"
                            rel="noreferrer"
                            className="rounded-full border border-ink/20 px-5 py-2.5 whitespace-nowrap text-ink transition-colors hover:border-ink"
                        >
                            Louer un photobooth
                        </a>
                    </div>

                    <div className="flex flex-wrap items-center gap-x-6 gap-y-2.5 text-[13px] text-muted">
                        <span className="flex items-center gap-3.5">
                            {SOCIAL_LINKS.map(({ label, href }) => {
                                const Icon = SOCIAL_ICONS[label];

                                return (
                                    <a
                                        key={label}
                                        href={href}
                                        target="_blank"
                                        rel="noreferrer"
                                        aria-label={label}
                                        className="flex text-muted transition-colors hover:text-ink"
                                    >
                                        <Icon />
                                    </a>
                                );
                            })}
                        </span>
                        <span className="text-line">|</span>
                        <a href="https://boothparty.fr/mentions-legales" target="_blank" rel="noreferrer" className="hover:text-ink">
                            Mentions légales
                        </a>
                        <a href="https://boothparty.fr/confidentialite" target="_blank" rel="noreferrer" className="hover:text-ink">
                            Confidentialité
                        </a>
                    </div>
                </div>
            </div>
        </footer>
    );
}
