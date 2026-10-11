import { Head } from '@inertiajs/react';
import { StatusBadge } from '@/components/admin/status-badge';

type Complaint = {
    reference: string;
    subject: string;
    category: string;
    status: string;
    status_label: string;
    area: string;
    submitted_at: string | null;
    attachments: number;
    resolution_summary: string | null;
    updates: { status: string; response: string | null; at: string }[];
};

export default function TrackComplaint({
    complaint,
}: {
    complaint: Complaint;
}) {
    return (
        <>
            <Head title={`Complaint ${complaint.reference}`}>
                <meta name="robots" content="noindex" />
                <meta name="referrer" content="no-referrer" />
            </Head>
            <div className="mx-auto max-w-2xl space-y-6 px-4 py-10">
                <div className="space-y-1">
                    <p className="font-mono text-sm text-muted-foreground">
                        {complaint.reference}
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {complaint.subject}
                    </h1>
                    <p className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        <StatusBadge
                            status={complaint.status}
                            label={complaint.status_label}
                        />
                        {complaint.category} · {complaint.area} · submitted{' '}
                        {complaint.submitted_at &&
                            new Date(
                                complaint.submitted_at,
                            ).toLocaleDateString()}
                        {complaint.attachments > 0 &&
                            ` · ${complaint.attachments} photo${complaint.attachments === 1 ? '' : 's'}`}
                    </p>
                </div>
                {complaint.resolution_summary && (
                    <div className="rounded-lg border p-4">
                        <div className="text-sm font-medium">Resolution</div>
                        <p className="text-sm whitespace-pre-line">
                            {complaint.resolution_summary}
                        </p>
                    </div>
                )}
                <section className="space-y-3">
                    <h2 className="font-medium">Progress</h2>
                    <ol className="space-y-3 border-l pl-4">
                        {complaint.updates.map((update, index) => (
                            <li key={`${update.at}-${index}`}>
                                <div className="text-sm font-medium">
                                    {update.status}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {new Date(update.at).toLocaleString()}
                                </div>
                                {update.response && (
                                    <p className="mt-1 text-sm whitespace-pre-line">
                                        {update.response}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ol>
                </section>
                <p className="text-xs text-muted-foreground">
                    Keep this link private. Anyone with it can see this page.
                </p>
            </div>
        </>
    );
}
