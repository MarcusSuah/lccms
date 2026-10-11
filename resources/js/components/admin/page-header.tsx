import type { ReactNode } from 'react';

export function PageHeader({
    title,
    description,
    actions,
    eyebrow,
}: {
    title: string;
    description?: ReactNode;
    actions?: ReactNode;
    eyebrow?: ReactNode;
}) {
    return (
        <header className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div className="space-y-1">
                {eyebrow && (
                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {eyebrow}
                    </div>
                )}
                <h1 className="text-xl font-semibold tracking-tight">
                    {title}
                </h1>
                {description && (
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </header>
    );
}
