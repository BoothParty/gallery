interface ApplicationLogoProps {
    mode?: 'light' | 'dark';
    showName?: boolean;
}

export default function ApplicationLogo({ mode = 'dark', showName = true }: ApplicationLogoProps) {
    return (
        <a href="https://boothparty.fr/" target="_blank" rel="noreferrer" className="inline-flex items-center">
            <img src="/icon.png" alt="Logo" className="h-12 w-12" />
            {showName && (
                <span
                    className={`ml-2 hidden font-syne text-2xl font-bold sm:block ${mode === 'light' ? 'text-white' : ''}`}
                >
                    BoothParty
                </span>
            )}
        </a>
    );
}
