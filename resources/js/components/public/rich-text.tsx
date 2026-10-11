import { cn } from '@/lib/utils';

/**
 * Renders HTML that the server produced from Markdown and sanitised with
 * HTMLPurifier against a strict allow-list. Never pass unsanitised input.
 */
export function RichText({
    html,
    className,
}: {
    html: string;
    className?: string;
}) {
    return (
        <div
            className={cn('rich-text', className)}
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}
