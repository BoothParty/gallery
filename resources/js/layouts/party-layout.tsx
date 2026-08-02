import type { ReactNode } from 'react';

import Footer from '@/components/party/footer';
import Navbar from '@/components/party/navbar';

interface PartyLayoutProps {
    children: ReactNode;
    partyId: number;
}

export default function PartyLayout({ children, partyId }: PartyLayoutProps) {
    return (
        <div className="flex min-h-screen flex-col justify-between font-sans antialiased">
            <Navbar />
            {children}
            <Footer partyId={partyId} />
        </div>
    );
}
