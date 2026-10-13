type AudiobookProps = {
    party: {
        title: string;
        event_at: string | null;
    };
};

export default function Audiobook({ party }: AudiobookProps) {
    return (
        <main className="min-h-screen bg-surface text-ink">
            <h1 className="font-serif text-4xl">{party.title}</h1>
        </main>
    );
}
