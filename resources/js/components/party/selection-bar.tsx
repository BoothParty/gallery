import type { FormEvent } from 'react';

import { MAX_STACK_THUMBNAILS, STACK_ELEMENT_ID, THUMBNAIL_OFFSET, THUMBNAIL_SIZE } from '@/components/party/selection-stack';
import type { ShareProgress, ShareStatus } from '@/hooks/use-photo-share';

export interface StackPhoto {
    id: number;
    thumbnailUrl: string | null;
}

interface SelectionBarProps {
    open: boolean;
    count: number;
    stack: StackPhoto[];
    selectedIds: number[];
    action: string;
    csrfToken: string;
    mode: 'share' | 'zip';
    shareStatus: ShareStatus;
    shareProgress: ShareProgress;
    onShare: () => void;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

export default function SelectionBar({
    open,
    count,
    stack,
    selectedIds,
    action,
    csrfToken,
    mode,
    shareStatus,
    shareProgress,
    onShare,
    onSubmit,
}: SelectionBarProps) {
    const shown = stack.slice(-MAX_STACK_THUMBNAILS);
    const stackWidth = shown.length === 0 ? 0 : (shown.length - 1) * THUMBNAIL_OFFSET + THUMBNAIL_SIZE;

    const sharing = mode === 'share';
    const busy = sharing && (shareStatus === 'preparing' || shareStatus === 'sharing');
    const downloading = sharing && shareStatus === 'preparing';
    const label = downloading
        ? `Téléchargement… ${shareProgress.done}/${shareProgress.total}`
        : sharing || count <= 1
          ? 'Télécharger'
          : 'Télécharger le ZIP';
    const ratio = shareProgress.total === 0 ? 0 : shareProgress.done / shareProgress.total;

    return (
        <div
            className="fixed inset-x-0 bottom-0 z-30 bg-ink/95 backdrop-blur-md transition-transform duration-300 ease-out"
            style={{ transform: open ? 'translateY(0)' : 'translateY(110%)' }}
            aria-hidden={!open}
        >
            <form
                data-download-form
                method="post"
                action={action}
                onSubmit={onSubmit}
                className="mx-auto flex max-w-[1440px] items-center justify-between gap-4 px-4 py-3.5 pb-[calc(0.875rem+env(safe-area-inset-bottom))] sm:px-8 lg:px-12"
            >
                <input type="hidden" name="_token" value={csrfToken} />
                {selectedIds.map((id) => (
                    <input key={id} type="hidden" name="ids[]" value={id} />
                ))}
                <div className="flex min-w-0 items-center gap-3.5">
                    <div
                        id={STACK_ELEMENT_ID}
                        className="relative h-[46px] flex-none transition-[width] duration-300"
                        style={{ width: `${stackWidth}px` }}
                    >
                        {shown.map((photo, index) => (
                            <div
                                key={photo.id}
                                className="absolute top-0 h-[46px] w-[46px] rounded-[3px] border-[1.5px] border-cream/90 bg-placeholder bg-cover bg-center shadow-lg transition-[left,transform] duration-300"
                                style={{
                                    left: `${index * THUMBNAIL_OFFSET}px`,
                                    zIndex: index + 1,
                                    transform: `rotate(${index % 2 ? 2.5 : -2.5}deg)`,
                                    backgroundImage: photo.thumbnailUrl === null ? undefined : `url("${photo.thumbnailUrl}")`,
                                }}
                            />
                        ))}
                    </div>

                    <div className="min-w-0 text-[13px] leading-tight text-cream/70">
                        <span data-selection-count className="block font-serif text-xl text-cream">
                            {count}
                        </span>
                        {count === 1 ? 'photo sélectionnée' : 'photos sélectionnées'}
                    </div>
                </div>

                <button
                    data-download-button
                    type={sharing ? 'button' : 'submit'}
                    onClick={sharing ? onShare : undefined}
                    disabled={count === 0 || busy}
                    className="relative overflow-hidden rounded-full px-5 py-3 text-sm whitespace-nowrap transition-opacity disabled:cursor-default enabled:cursor-pointer enabled:bg-cream enabled:text-ink disabled:bg-cream/20 disabled:text-cream/50"
                >
                    {label}
                    {downloading && (
                        <span
                            data-download-progress
                            className="absolute inset-x-0 bottom-0 h-[3px] origin-left bg-ink/40 transition-transform duration-200"
                            style={{ transform: `scaleX(${ratio})` }}
                        />
                    )}
                </button>
            </form>
        </div>
    );
}
