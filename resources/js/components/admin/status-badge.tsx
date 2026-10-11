import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const tones: Record<string, string> = {
    positive:
        'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    warning:
        'border-transparent bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-300',
    negative:
        'border-transparent bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    info: 'border-transparent bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    neutral: 'border-transparent bg-muted text-muted-foreground',
};

const statusTone: Record<string, keyof typeof tones> = {
    active: 'positive',
    verified: 'positive',
    approved: 'positive',
    applied: 'positive',
    committed: 'positive',
    resolved: 'positive',
    published: 'positive',
    synced: 'positive',
    completed: 'positive',
    present: 'positive',
    functional: 'positive',
    operational: 'positive',
    good: 'positive',
    submitted: 'info',
    validated: 'info',
    in_review: 'info',
    under_review: 'info',
    assigned: 'info',
    in_progress: 'info',
    received: 'info',
    planned: 'info',
    scheduled: 'info',
    queued: 'info',
    running: 'info',
    pending: 'warning',
    unverified: 'warning',
    draft: 'warning',
    awaiting_information: 'warning',
    open: 'warning',
    conflict: 'warning',
    fair: 'warning',
    partially_functional: 'warning',
    not_assessed: 'warning',
    rejected: 'negative',
    invalid: 'negative',
    failed: 'negative',
    poor: 'negative',
    closed_down: 'negative',
    not_functional: 'negative',
    verified_absent: 'neutral',
    inactive: 'neutral',
    archived: 'neutral',
    discarded: 'neutral',
    closed: 'neutral',
    uploaded: 'neutral',
};

export function humanize(value: string): string {
    const text = value.replace(/[_-]+/g, ' ');

    return text.charAt(0).toUpperCase() + text.slice(1);
}

export function StatusBadge({
    status,
    label,
    className,
}: {
    status: string;
    label?: string;
    className?: string;
}) {
    return (
        <Badge
            variant="outline"
            className={cn(tones[statusTone[status] ?? 'neutral'], className)}
        >
            {label ?? humanize(status)}
        </Badge>
    );
}
