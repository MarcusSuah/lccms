import {
    AlertTriangle,
    CheckCircle2,
    CloudUpload,
    GitMerge,
} from 'lucide-react';
import { cn } from '@/lib/utils';

const states = {
    synced: {
        icon: CheckCircle2,
        label: 'Synced',
        className: 'text-emerald-700 dark:text-emerald-400',
    },
    pending: {
        icon: CloudUpload,
        label: 'Waiting to sync',
        className: 'text-sky-700 dark:text-sky-400',
    },
    failed: {
        icon: AlertTriangle,
        label: 'Needs attention',
        className: 'text-red-700 dark:text-red-400',
    },
    conflict: {
        icon: GitMerge,
        label: 'Conflict',
        className: 'text-amber-700 dark:text-amber-400',
    },
} as const;

/**
 * Sync state of a local record, shown with an icon and text (never colour alone).
 */
export function SyncBadge({
    state,
    className,
}: {
    state: keyof typeof states;
    className?: string;
}) {
    const { icon: Icon, label, className: tone } = states[state];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 text-xs',
                tone,
                className,
            )}
        >
            <Icon className="size-3.5" aria-hidden />
            {label}
        </span>
    );
}
