import type { ReactNode } from 'react';

import Footer from '@/components/party/footer';

interface PartyLayoutProps {
    children: ReactNode;
}

export default function PartyLayout({ children }: PartyLayoutProps) {
    return (
        <div className="flex min-h-screen flex-col bg-surface font-sans text-ink antialiased">
            {children}
            <Footer />
        </div>
    );
}
