import { Head, router } from '@inertiajs/react';
import { Inbox as InboxIcon } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { FilterBar } from '@/components/admin/filter-bar';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import { Card, CardContent } from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/native-select';
import { index, update } from '@/routes/admin/content/inbox';
import type { Option, SimplePaginated } from '@/types';

type Message = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    subject: string;
    message: string;
    internal_note: string | null;
    status: string;
    handled_by: string | null;
    created_at: string | null;
};

export default function Inbox({
    messages,
    filters,
    statusOptions,
}: {
    messages: SimplePaginated<Message>;
    filters: { status: string };
    statusOptions: Option[];
}) {
    return (
        <>
            <Head title="Contact inbox" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Public website"
                    title="Contact inbox"
                    description="Messages sent through the public contact form."
                />
                <FilterBar
                    filters={filters}
                    selects={[
                        {
                            name: 'status',
                            label: 'Status',
                            options: statusOptions,
                        },
                    ]}
                />
                {messages.data.length === 0 ? (
                    <EmptyState icon={InboxIcon} title="No messages" />
                ) : (
                    <div className="grid gap-3">
                        {messages.data.map((message) => (
                            <Card key={message.id}>
                                <CardContent className="space-y-2 pt-6">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <div className="font-medium">
                                                {message.subject}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {message.name} ·{' '}
                                                <a
                                                    href={`mailto:${message.email}`}
                                                    className="underline"
                                                >
                                                    {message.email}
                                                </a>
                                                {message.phone &&
                                                    ` · ${message.phone}`}{' '}
                                                ·{' '}
                                                {message.created_at &&
                                                    new Date(
                                                        message.created_at,
                                                    ).toLocaleString()}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <StatusBadge
                                                status={message.status}
                                            />
                                            <NativeSelect
                                                aria-label="Change status"
                                                className="h-8 w-36"
                                                value={message.status}
                                                onChange={(event) =>
                                                    router.patch(
                                                        update.url(message.id),
                                                        {
                                                            status: event.target
                                                                .value,
                                                            internal_note:
                                                                message.internal_note,
                                                        },
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                {statusOptions.map((option) => (
                                                    <option
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                        </div>
                                    </div>
                                    <p className="text-sm whitespace-pre-line">
                                        {message.message}
                                    </p>
                                    {message.handled_by && (
                                        <p className="text-xs text-muted-foreground">
                                            Handled by {message.handled_by}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
                <Pagination
                    links={messages.links}
                    from={messages.from}
                    to={messages.to}
                    total={messages.total}
                />
            </div>
        </>
    );
}

Inbox.layout = {
    breadcrumbs: [{ title: 'Contact inbox', href: index() }],
};
