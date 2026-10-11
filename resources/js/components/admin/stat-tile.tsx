import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * A single headline figure. Text uses ink tokens only; a missing value is
 * shown as "Not enumerated" or similar rather than as zero.
 */
export function StatTile({
    label,
    value,
    hint,
    icon: Icon,
    emptyLabel = 'No data',
    className,
}: {
    label: string;
    value: number | string | null | undefined;
    hint?: ReactNode;
    icon?: LucideIcon;
    emptyLabel?: string;
    className?: string;
}) {
    const isEmpty = value === null || value === undefined;

    return (
        <div className={cn('rounded-lg border bg-card p-4', className)}>
            {Icon && (
                <Icon className="mb-2 size-5 text-foreground" aria-hidden />
            )}
            <div className="text-xs font-medium text-muted-foreground">
                {label}
            </div>
            <div
                className={cn(
                    'mt-1 text-2xl font-semibold tracking-tight tabular-nums',
                    isEmpty && 'text-base font-medium text-muted-foreground',
                )}
            >
                {isEmpty
                    ? emptyLabel
                    : typeof value === 'number'
                      ? value.toLocaleString()
                      : value}
            </div>
            {hint && (
                <div className="mt-1 text-xs text-muted-foreground">{hint}</div>
            )}
        </div>
    );
}
