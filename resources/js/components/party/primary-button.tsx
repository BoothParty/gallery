import type { ReactNode } from 'react';

interface PrimaryButtonProps {
    text: string;
    icon?: ReactNode;
    url?: string;
    downloadable?: boolean;
    disabled?: boolean;
    onClick?: () => void;
    className?: string;
    defaultClassName?: string;
    paddings?: string;
}

export default function PrimaryButton({
    text,
    icon,
    url,
    downloadable = false,
    disabled = false,
    onClick,
    className = '',
    defaultClassName = 'rounded-full bg-primary text-sm sm:text-base font-medium flex gap-2 items-center justify-center text-white disabled:opacity-50 disabled:cursor-not-allowed',
    paddings = 'py-3 px-4',
}: PrimaryButtonProps) {
    const classes = `${defaultClassName} ${paddings} ${className}`;

    if (url) {
        return (
            <a
                href={url}
                className={classes}
                {...(downloadable ? { download: true, target: '_blank', rel: 'noreferrer' } : {})}
            >
                {icon}
                {text}
            </a>
        );
    }

    return (
        <button className={classes} disabled={disabled} onClick={onClick}>
            {icon}
            {text}
        </button>
    );
}
