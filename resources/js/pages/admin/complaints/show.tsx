import { Head, useForm } from '@inertiajs/react';
import { FormField } from '@/components/admin/form-field';
import { PageHeader } from '@/components/admin/page-header';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import {
    assign,
    index,
    status as statusRoute,
} from '@/routes/admin/complaints';
import { store as storeNote } from '@/routes/admin/complaints/notes';
import type { Option } from '@/types';

type Complaint = {
    reference: string;
    subject: string;
    category: string;
    description: string;
    status: string;
    priority: string;
    location: string[];
    house_reference: string | null;
    is_anonymous: boolean;
    complainant: {
        name: string | null;
        phone: string | null;
        email: string | null;
    } | null;
    assignee: { id: number; name: string } | null;
    resolution_summary: string | null;
    created_at: string | null;
    allowed_transitions: Option[];
    attachments: { slot: number; thumbnail_url: string; url: string }[];
    history: {
        from: string | null;
        to: string;
        public_response: string | null;
        actor: string;
        at: string;
    }[];
    notes: { id: number; body: string; author: string | null; at: string }[];
};

export default function ComplaintShow({
    complaint,
    officers,
    priorityOptions,
    can,
}: {
    complaint: Complaint;
    officers: Option<number>[];
    priorityOptions: Option[];
    can: { manage: boolean; assign: boolean };
}) {
    const transition = useForm({
        status: complaint.allowed_transitions[0]?.value ?? '',
        public_response: '',
        resolution_summary: '',
    });
    const assignment = useForm({
        assigned_to: complaint.assignee?.id ?? 0,
        priority: complaint.priority,
    });
    const note = useForm({ body: '' });

    return (
        <>
            <Head title={`Complaint ${complaint.reference}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow={`${complaint.category} · ${complaint.reference}`}
                    title={complaint.subject}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <StatusBadge status={complaint.status} />
                            {complaint.location.join(' › ')} · received{' '}
                            {complaint.created_at &&
                                new Date(complaint.created_at).toLocaleString()}
                        </span>
                    }
                />
                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Complaint</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <p className="whitespace-pre-line">
                                    {complaint.description}
                                </p>
                                {complaint.house_reference && (
                                    <p className="text-muted-foreground">
                                        House reference:{' '}
                                        {complaint.house_reference}
                                    </p>
                                )}
                                {complaint.attachments.length > 0 && (
                                    <ul className="grid grid-cols-3 gap-2 sm:grid-cols-5">
                                        {complaint.attachments.map(
                                            (attachment) => (
                                                <li key={attachment.slot}>
                                                    <a
                                                        href={attachment.url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <img
                                                            src={
                                                                attachment.thumbnail_url
                                                            }
                                                            alt={`Attachment ${attachment.slot}`}
                                                            className="aspect-square w-full rounded-md border object-cover"
                                                        />
                                                    </a>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>History</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ol className="space-y-3 text-sm">
                                    {complaint.history.map(
                                        (entry, position) => (
                                            <li key={`${entry.at}-${position}`}>
                                                <span className="font-medium">
                                                    {entry.to}
                                                </span>{' '}
                                                <span className="text-muted-foreground">
                                                    by {entry.actor} ·{' '}
                                                    {new Date(
                                                        entry.at,
                                                    ).toLocaleString()}
                                                </span>
                                                {entry.public_response && (
                                                    <p className="mt-1 rounded bg-muted/50 p-2">
                                                        Response to complainant:{' '}
                                                        {entry.public_response}
                                                    </p>
                                                )}
                                            </li>
                                        ),
                                    )}
                                </ol>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Internal notes</CardTitle>
                                <p className="text-xs text-muted-foreground">
                                    Never shown to the complainant.
                                </p>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {can.manage && (
                                    <form
                                        className="space-y-2"
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            note.submit(
                                                storeNote(complaint.reference),
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: () =>
                                                        note.reset(),
                                                },
                                            );
                                        }}
                                    >
                                        <Textarea
                                            aria-label="Internal note"
                                            value={note.data.body}
                                            onChange={(event) =>
                                                note.setData(
                                                    'body',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <Button
                                            type="submit"
                                            size="sm"
                                            disabled={
                                                !note.data.body ||
                                                note.processing
                                            }
                                        >
                                            Add note
                                        </Button>
                                    </form>
                                )}
                                <ul className="space-y-2 text-sm">
                                    {complaint.notes.map((item) => (
                                        <li
                                            key={item.id}
                                            className="rounded-md border p-2"
                                        >
                                            <p className="whitespace-pre-line">
                                                {item.body}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {item.author} ·{' '}
                                                {new Date(
                                                    item.at,
                                                ).toLocaleString()}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Complainant</CardTitle>
                            </CardHeader>
                            <CardContent className="text-sm">
                                {complaint.is_anonymous ||
                                !complaint.complainant ? (
                                    <p className="text-muted-foreground">
                                        Anonymous. Follow-up is only possible
                                        through the public tracking page.
                                    </p>
                                ) : (
                                    <dl className="space-y-1">
                                        <div>
                                            {complaint.complainant.name ??
                                                'Name not given'}
                                        </div>
                                        {complaint.complainant.phone && (
                                            <div>
                                                {complaint.complainant.phone}
                                            </div>
                                        )}
                                        {complaint.complainant.email && (
                                            <div>
                                                {complaint.complainant.email}
                                            </div>
                                        )}
                                    </dl>
                                )}
                            </CardContent>
                        </Card>

                        {can.assign && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Assignment</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <form
                                        className="space-y-3"
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            assignment.submit(
                                                assign(complaint.reference),
                                                { preserveScroll: true },
                                            );
                                        }}
                                    >
                                        <FormField
                                            label="Officer"
                                            htmlFor="assigned_to"
                                            error={
                                                assignment.errors.assigned_to
                                            }
                                        >
                                            <NativeSelect
                                                id="assigned_to"
                                                value={
                                                    assignment.data
                                                        .assigned_to || ''
                                                }
                                                onChange={(event) =>
                                                    assignment.setData(
                                                        'assigned_to',
                                                        Number(
                                                            event.target.value,
                                                        ),
                                                    )
                                                }
                                            >
                                                <option value="">
                                                    Choose…
                                                </option>
                                                {officers.map((officer) => (
                                                    <option
                                                        key={officer.value}
                                                        value={officer.value}
                                                    >
                                                        {officer.label}
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                        </FormField>
                                        <FormField
                                            label="Priority"
                                            htmlFor="priority"
                                        >
                                            <NativeSelect
                                                id="priority"
                                                value={assignment.data.priority}
                                                onChange={(event) =>
                                                    assignment.setData(
                                                        'priority',
                                                        event.target.value,
                                                    )
                                                }
                                            >
                                                {priorityOptions.map(
                                                    (option) => (
                                                        <option
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </option>
                                                    ),
                                                )}
                                            </NativeSelect>
                                        </FormField>
                                        <Button
                                            type="submit"
                                            size="sm"
                                            disabled={
                                                !assignment.data.assigned_to ||
                                                assignment.processing
                                            }
                                        >
                                            Assign
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                        )}

                        {can.manage &&
                            complaint.allowed_transitions.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Update status</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <form
                                            className="space-y-3"
                                            onSubmit={(event) => {
                                                event.preventDefault();
                                                transition.submit(
                                                    statusRoute(
                                                        complaint.reference,
                                                    ),
                                                    {
                                                        preserveScroll: true,
                                                        onSuccess: () =>
                                                            transition.reset(
                                                                'public_response',
                                                                'resolution_summary',
                                                            ),
                                                    },
                                                );
                                            }}
                                        >
                                            <FormField
                                                label="New status"
                                                htmlFor="new_status"
                                                error={transition.errors.status}
                                            >
                                                <NativeSelect
                                                    id="new_status"
                                                    value={
                                                        transition.data.status
                                                    }
                                                    onChange={(event) =>
                                                        transition.setData(
                                                            'status',
                                                            event.target.value,
                                                        )
                                                    }
                                                >
                                                    {complaint.allowed_transitions.map(
                                                        (option) => (
                                                            <option
                                                                key={
                                                                    option.value
                                                                }
                                                                value={
                                                                    option.value
                                                                }
                                                            >
                                                                {option.label}
                                                            </option>
                                                        ),
                                                    )}
                                                </NativeSelect>
                                            </FormField>
                                            <FormField
                                                label="Response to the complainant"
                                                htmlFor="public_response"
                                                error={
                                                    transition.errors
                                                        .public_response
                                                }
                                                hint="Shown on the tracking page and emailed if they gave an address."
                                            >
                                                <Textarea
                                                    id="public_response"
                                                    value={
                                                        transition.data
                                                            .public_response
                                                    }
                                                    onChange={(event) =>
                                                        transition.setData(
                                                            'public_response',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </FormField>
                                            {transition.data.status ===
                                                'resolved' && (
                                                <FormField
                                                    label="Resolution summary"
                                                    htmlFor="resolution_summary"
                                                    error={
                                                        transition.errors
                                                            .resolution_summary
                                                    }
                                                >
                                                    <Textarea
                                                        id="resolution_summary"
                                                        value={
                                                            transition.data
                                                                .resolution_summary
                                                        }
                                                        onChange={(event) =>
                                                            transition.setData(
                                                                'resolution_summary',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                            )}
                                            <Button
                                                type="submit"
                                                size="sm"
                                                disabled={transition.processing}
                                            >
                                                Save status
                                            </Button>
                                        </form>
                                    </CardContent>
                                </Card>
                            )}
                    </div>
                </div>
            </div>
        </>
    );
}

ComplaintShow.layout = {
    breadcrumbs: [{ title: 'Complaints', href: index() }],
};
