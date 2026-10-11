import { Head, Link, useForm } from '@inertiajs/react';
import { FormField } from '@/components/admin/form-field';
import { PageHeader } from '@/components/admin/page-header';
import { CommunityBlockField } from '@/components/geo/community-block-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index, store, update } from '@/routes/admin/leadership';
import type { Option } from '@/types';

type PositionOption = {
    value: number;
    label: string;
    level: string;
    single_holder: boolean;
};

type Tenure = {
    id: number;
    position_id: number;
    community_id: number;
    block_id: number | null;
    community: { id: number; name: string } | null;
    block: { id: number; name: string } | null;
    holder_name: string;
    contact_phone: string | null;
    starts_on: string;
    ends_on: string | null;
    selection_method: string;
    verification_status: string;
    is_public: boolean;
    exception_note: string | null;
    notes: string | null;
};

export default function LeadershipForm({
    tenure,
    positions,
    selectionMethods,
    verification,
}: {
    tenure: Tenure | null;
    positions: PositionOption[];
    selectionMethods: Option[];
    verification: Option[];
}) {
    const form = useForm({
        position_id: tenure?.position_id ?? 0,
        community_id: tenure?.community_id ?? (null as number | null),
        block_id: tenure?.block_id ?? (null as number | null),
        location_label: tenure?.community
            ? `${tenure.community.name}${tenure.block ? ` › ${tenure.block.name}` : ''}`
            : '',
        holder_name: tenure?.holder_name ?? '',
        contact_phone: tenure?.contact_phone ?? '',
        starts_on: tenure?.starts_on ?? '',
        ends_on: tenure?.ends_on ?? '',
        selection_method: tenure?.selection_method ?? 'elected',
        verification_status: tenure?.verification_status ?? 'unverified',
        is_public: tenure?.is_public ?? false,
        exception_note: tenure?.exception_note ?? '',
        notes: tenure?.notes ?? '',
        close_previous: true,
        document: null as File | null,
    });
    const position = positions.find(
        (option) => option.value === form.data.position_id,
    );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            ends_on: data.ends_on || null,
            exception_note: data.exception_note || null,
            block_id: position?.level === 'block' ? data.block_id : null,
            ...(tenure ? { _method: 'put' } : {}),
        }));

        // File uploads must be sent as multipart POST; Laravel reads _method for updates.
        form.post(tenure ? update.url(tenure.id) : store.url(), {
            forceFormData: true,
        });
    };

    return (
        <>
            <Head
                title={tenure ? `Edit ${tenure.holder_name}` : 'Record leader'}
            />
            <form
                onSubmit={submit}
                className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6"
            >
                <PageHeader
                    eyebrow="Community governance"
                    title={
                        tenure
                            ? `Edit ${tenure.holder_name}`
                            : 'Record a leader'
                    }
                    description="To record a successor, add a new leader: the predecessor’s open tenure is closed the day before the new one starts."
                />
                <Card>
                    <CardHeader>
                        <CardTitle>Position and area</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <FormField
                            label="Position"
                            htmlFor="position_id"
                            error={form.errors.position_id}
                            required
                        >
                            <NativeSelect
                                id="position_id"
                                value={form.data.position_id || ''}
                                onChange={(event) =>
                                    form.setData(
                                        'position_id',
                                        Number(event.target.value),
                                    )
                                }
                            >
                                <option value="">Select…</option>
                                {positions.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label} ({option.level})
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                        <CommunityBlockField
                            value={{
                                community_id: form.data.community_id,
                                block_id: form.data.block_id,
                                label: form.data.location_label,
                            }}
                            onChange={(value) =>
                                form.setData((data) => ({
                                    ...data,
                                    community_id: value.community_id,
                                    block_id: value.block_id,
                                    location_label: value.label,
                                }))
                            }
                            blockRequired={position?.level === 'block'}
                            error={
                                form.errors.community_id ?? form.errors.block_id
                            }
                        />
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Leader and tenure</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Name"
                            htmlFor="holder_name"
                            error={form.errors.holder_name}
                            required
                        >
                            <Input
                                id="holder_name"
                                value={form.data.holder_name}
                                onChange={(event) =>
                                    form.setData(
                                        'holder_name',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Phone (staff only)"
                            htmlFor="contact_phone"
                            error={form.errors.contact_phone}
                        >
                            <Input
                                id="contact_phone"
                                value={form.data.contact_phone}
                                onChange={(event) =>
                                    form.setData(
                                        'contact_phone',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Start date"
                            htmlFor="starts_on"
                            error={form.errors.starts_on}
                            required
                        >
                            <Input
                                id="starts_on"
                                type="date"
                                value={form.data.starts_on}
                                onChange={(event) =>
                                    form.setData(
                                        'starts_on',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="End date"
                            htmlFor="ends_on"
                            error={form.errors.ends_on}
                            hint="Leave empty while the leader is in office."
                        >
                            <Input
                                id="ends_on"
                                type="date"
                                value={form.data.ends_on}
                                onChange={(event) =>
                                    form.setData('ends_on', event.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="How selected"
                            htmlFor="selection_method"
                            error={form.errors.selection_method}
                        >
                            <NativeSelect
                                id="selection_method"
                                value={form.data.selection_method}
                                onChange={(event) =>
                                    form.setData(
                                        'selection_method',
                                        event.target.value,
                                    )
                                }
                            >
                                {selectionMethods.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                        <FormField
                            label="Verification"
                            htmlFor="verification_status"
                            error={form.errors.verification_status}
                        >
                            <NativeSelect
                                id="verification_status"
                                value={form.data.verification_status}
                                onChange={(event) =>
                                    form.setData(
                                        'verification_status',
                                        event.target.value,
                                    )
                                }
                            >
                                {verification.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                        <FormField
                            label="Supporting document (PDF or image, optional)"
                            htmlFor="document"
                            error={form.errors.document}
                            className="sm:col-span-2"
                        >
                            <Input
                                id="document"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                onChange={(event) =>
                                    form.setData(
                                        'document',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Notes"
                            htmlFor="notes"
                            error={form.errors.notes}
                            className="sm:col-span-2"
                        >
                            <Textarea
                                id="notes"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                            />
                        </FormField>
                        {!tenure && (
                            <label className="flex items-center gap-2 text-sm sm:col-span-2">
                                <Checkbox
                                    checked={form.data.close_previous}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'close_previous',
                                            checked === true,
                                        )
                                    }
                                />
                                Close the current holder’s tenure the day before
                                this one starts
                            </label>
                        )}
                        <div className="flex items-center gap-2 sm:col-span-2">
                            <Checkbox
                                id="is_public"
                                checked={form.data.is_public}
                                onCheckedChange={(checked) =>
                                    form.setData('is_public', checked === true)
                                }
                            />
                            <Label htmlFor="is_public">
                                List in the public leadership directory (name,
                                position and dates only)
                            </Label>
                        </div>
                        {position?.single_holder && (
                            <FormField
                                label="Authorised exception (only if two people legitimately hold this position at once)"
                                htmlFor="exception_note"
                                error={form.errors.exception_note}
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    id="exception_note"
                                    value={form.data.exception_note}
                                    onChange={(event) =>
                                        form.setData(
                                            'exception_note',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        )}
                    </CardContent>
                </Card>
                <div className="flex justify-end gap-2">
                    <Button asChild variant="ghost">
                        <Link href={index()}>Cancel</Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {tenure ? 'Save changes' : 'Record leader'}
                    </Button>
                </div>
            </form>
        </>
    );
}

LeadershipForm.layout = {
    breadcrumbs: [{ title: 'Leadership', href: index() }],
};
