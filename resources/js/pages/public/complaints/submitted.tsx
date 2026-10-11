import { Head } from '@inertiajs/react';
import { CheckCircle2, Copy } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';

export default function ComplaintSubmitted({
    submission,
}: {
    submission: {
        reference: string;
        tracking_url: string;
        has_contact: boolean;
    };
}) {
    const [copied, copy] = useClipboard();

    return (
        <>
            <Head title="Complaint received" />
            <div className="mx-auto max-w-2xl space-y-6 px-4 py-12">
                <div className="flex items-center gap-3">
                    <CheckCircle2 className="size-8 text-emerald-600" />
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Your complaint was received
                    </h1>
                </div>
                <p>
                    Reference:{' '}
                    <span className="font-mono font-semibold">
                        {submission.reference}
                    </span>
                </p>
                <div className="space-y-2 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950">
                    <p className="font-medium">
                        Save your private tracking link now
                    </p>
                    <p className="text-sm">
                        This link is the only way to follow your complaint. It
                        is shown only once and we cannot recover it. Do not
                        share it with others.
                    </p>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <code className="flex-1 rounded border bg-background p-2 text-xs break-all">
                            {submission.tracking_url}
                        </code>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => void copy(submission.tracking_url)}
                        >
                            <Copy /> {copied ? 'Copied' : 'Copy link'}
                        </Button>
                    </div>
                </div>
                {!submission.has_contact && (
                    <p className="text-sm text-muted-foreground">
                        You did not leave contact details, so officers cannot
                        reach you for more information. Check the tracking link
                        for updates.
                    </p>
                )}
                <Button asChild>
                    <a href={submission.tracking_url}>Open tracking page</a>
                </Button>
            </div>
        </>
    );
}
