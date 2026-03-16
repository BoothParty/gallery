import ApplicationLogo from '@/components/application-logo';

interface FooterProps {
    partyId?: number;
}

export default function Footer({ partyId }: FooterProps) {
    return (
        <footer className="container mx-auto mt-12 flex flex-row items-center justify-around gap-2 p-6">
            <ApplicationLogo mode="light" />
            <div>
                <ul className="flex flex-col gap-1 font-syne text-sm font-medium text-white md:flex-row md:gap-4 md:text-lg lg:justify-around">
                    <li>
                        <span className="cursor-pointer hover:text-primary">Espace hôte</span>
                    </li>
                    <li>
                        <span className="cursor-pointer hover:text-primary">Mentions légales</span>
                    </li>
                </ul>
            </div>
            <div>
                <a
                    href="https://boothparty.fr"
                    target="_blank"
                    rel="noreferrer"
                    className="rounded-full bg-primary px-4 py-2"
                >
                    <span className="font-syne text-lg font-medium text-white">Louez moi</span>
                </a>
            </div>
        </footer>
    );
}
