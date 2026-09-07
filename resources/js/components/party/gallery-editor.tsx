import { useForm } from '@inertiajs/react';
import { useEffect, useRef, useState, type FormEvent } from 'react';

import CoverCropper from '@/components/party/cover-cropper';
import useCoverCrop, { type CoverCrop } from '@/hooks/use-cover-crop';

const TITLE_LIMIT = 60;
const SUBTITLE_LIMIT = 90;

interface GalleryEditorProps {
    open: boolean;
    onClose: () => void;
    partyName: string;
    title: string;
    subtitle: string | null;
    bannerUrl: string | null;
    crop: CoverCrop;
    onSaved: () => void;
}

export default function GalleryEditor({
    open,
    onClose,
    partyName,
    title,
    subtitle,
    bannerUrl,
    crop,
    onSaved,
}: GalleryEditorProps) {
    const form = useForm({
        display_name: title,
        description: subtitle ?? '',
        banner: null as File | null,
        remove_banner: false,
        banner_position_x: String(crop.x),
        banner_position_y: String(crop.y),
        banner_zoom: String(crop.zoom),
    });

    const cropper = useCoverCrop(crop);
    const [preview, setPreview] = useState<string | null>(null);
    const objectUrl = useRef<string | null>(null);

    /**
     * Reload the draft from the stored values every time the modal opens, so
     * that cancelling really throws the previous draft away.
     */
    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData({
            display_name: title,
            description: subtitle ?? '',
            banner: null,
            remove_banner: false,
            banner_position_x: String(crop.x),
            banner_position_y: String(crop.y),
            banner_zoom: String(crop.zoom),
        });
        form.clearErrors();
        cropper.setCrop(crop);
        setPreview(null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    /** Mirror the cropper, which owns the framing while the modal is open. */
    useEffect(() => {
        form.setData((current) => ({
            ...current,
            banner_position_x: String(cropper.crop.x),
            banner_position_y: String(cropper.crop.y),
            banner_zoom: String(cropper.crop.zoom),
        }));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [cropper.crop]);

    useEffect(
        () => () => {
            if (objectUrl.current !== null) {
                URL.revokeObjectURL(objectUrl.current);
            }
        },
        [],
    );

    const onPick = (file: File) => {
        if (objectUrl.current !== null) {
            URL.revokeObjectURL(objectUrl.current);
        }

        objectUrl.current = URL.createObjectURL(file);

        form.setData((current) => ({ ...current, banner: file, remove_banner: false }));
        setPreview(objectUrl.current);
        cropper.reset();
    };

    /**
     * Drops the cover from the draft. The gallery only falls back to its
     * typographic hero once the submission goes through.
     */
    const onRemove = () => {
        if (objectUrl.current !== null) {
            URL.revokeObjectURL(objectUrl.current);
            objectUrl.current = null;
        }

        form.setData((current) => ({ ...current, banner: null, remove_banner: true }));
        setPreview(null);
        cropper.reset();
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.post(`/${encodeURIComponent(partyName)}/metadata`, {
            preserveScroll: true,
            onSuccess: () => {
                onSaved();
                onClose();
            },
        });
    };

    const source = form.data.remove_banner ? null : (preview ?? bannerUrl);
    const subtitleLeft = SUBTITLE_LIMIT - form.data.description.length;

    return (
        <>
            <div
                onClick={onClose}
                className="fixed inset-0 z-50 bg-shade/50 backdrop-blur-[3px] transition-opacity duration-250"
                style={{ opacity: open ? 1 : 0, pointerEvents: open ? 'auto' : 'none' }}
            />

            <form
                data-gallery-editor
                onSubmit={submit}
                role="dialog"
                aria-label="Modifier la galerie"
                aria-hidden={!open}
                className="fixed top-1/2 left-1/2 z-[51] flex max-h-[min(88vh,860px)] w-[min(620px,calc(100vw-24px))] flex-col rounded-md bg-surface shadow-[0_30px_80px_rgba(20,18,15,0.35)] transition-all duration-250"
                style={{
                    opacity: open ? 1 : 0,
                    pointerEvents: open ? 'auto' : 'none',
                    transform: `translate(-50%, -50%) scale(${open ? 1 : 0.97})`,
                }}
            >
                <div className="flex items-center justify-between gap-4 border-b border-line px-5 pt-5 pb-4 lg:px-7">
                    <span className="min-w-0 font-serif text-[22px] leading-tight">Modifier la galerie</span>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Fermer"
                        className="flex h-9 w-9 flex-none cursor-pointer items-center justify-center rounded-full border border-ink/15 text-ink transition-colors hover:border-ink"
                    >
                        <svg
                            width="13"
                            height="13"
                            viewBox="0 0 14 14"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="1.5"
                            strokeLinecap="round"
                            aria-hidden="true"
                        >
                            <path d="M2.5 2.5l9 9m0-9l-9 9" />
                        </svg>
                    </button>
                </div>

                <div className="flex flex-col gap-6 overflow-y-auto px-5 py-5 lg:px-7 lg:py-7">
                    <label className="flex flex-col gap-2">
                        <span className="text-[11px] tracking-[0.16em] text-muted uppercase">Nom de la galerie</span>
                        <input
                            value={form.data.display_name}
                            onChange={(event) => form.setData('display_name', event.target.value)}
                            maxLength={TITLE_LIMIT}
                            placeholder="Camille & Antoine"
                            className="w-full rounded-[3px] border border-ink/15 bg-cream px-3.5 py-3 font-serif text-2xl text-ink outline-none focus:border-ink"
                        />
                        {form.errors.display_name && (
                            <span className="text-xs text-red-600">{form.errors.display_name}</span>
                        )}
                    </label>

                    <label className="flex flex-col gap-2">
                        <span className="text-[11px] tracking-[0.16em] text-muted uppercase">Description</span>
                        <input
                            value={form.data.description}
                            onChange={(event) => form.setData('description', event.target.value)}
                            maxLength={SUBTITLE_LIMIT}
                            placeholder="Date · Lieu"
                            className="w-full rounded-[3px] border border-ink/15 bg-cream px-3.5 py-3 text-sm text-ink outline-none focus:border-ink"
                        />
                        <span className="text-xs text-subtle">
                            Affichée sous le titre, en petites capitales. {subtitleLeft} caractères restants.
                        </span>
                        {form.errors.description && (
                            <span className="text-xs text-red-600">{form.errors.description}</span>
                        )}
                    </label>

                    <div className="flex flex-col gap-3">
                        <CoverCropper
                            src={source}
                            crop={cropper.crop}
                            frameRef={cropper.frameRef}
                            onImageLoad={cropper.onImageLoad}
                            onPointerDown={cropper.onPointerDown}
                            onZoomChange={cropper.setZoom}
                            onReset={cropper.reset}
                            onPick={onPick}
                            onRemove={onRemove}
                        />
                        {form.errors.banner && <span className="text-xs text-red-600">{form.errors.banner}</span>}
                    </div>
                </div>

                <div className="flex items-center justify-end gap-2.5 border-t border-line bg-surface/70 px-5 py-4 lg:px-7">
                    <button
                        type="button"
                        onClick={onClose}
                        className="cursor-pointer rounded-full px-5 py-3 text-sm text-muted transition-colors hover:text-ink"
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="cursor-pointer rounded-full bg-ink px-6 py-3 text-sm text-cream transition-opacity disabled:opacity-60"
                    >
                        Enregistrer
                    </button>
                </div>
            </form>
        </>
    );
}
