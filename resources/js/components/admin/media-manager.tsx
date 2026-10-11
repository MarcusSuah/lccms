import { router, useForm } from '@inertiajs/react';
import { ImagePlus, Star, Trash2 } from 'lucide-react';
import { FormField } from '@/components/admin/form-field';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { destroy, store, update } from '@/routes/admin/content/media';

export type OwnerMedia = {
    uuid: string;
    caption: string | null;
    alt_text: string | null;
    visibility: string;
    approval_status: string;
    consent_status: string;
    shows_identifiable_people: boolean;
    shows_children: boolean;
    is_featured: boolean;
    thumbnail_url: string;
};

/**
 * Upload and manage photographs for one record. New photos are staff-only and
 * pending until someone with media approval publishes them.
 */
export function MediaManager({
    ownerType,
    ownerId,
    media,
    canManage,
}: {
    ownerType: 'community' | 'facility' | 'infrastructure' | 'event' | 'post';
    ownerId: number;
    media: OwnerMedia[];
    canManage: boolean;
}) {
    const form = useForm({
        owner_type: ownerType,
        owner_id: ownerId,
        image: null as File | null,
        caption: '',
        alt_text: '',
        visibility: 'public',
        consent_status: 'not_required',
        shows_identifiable_people: false,
        shows_children: false,
    });

    return (
        <Card>
            <CardHeader>
                <CardTitle>Photographs</CardTitle>
                <p className="text-sm text-muted-foreground">
                    Obtain consent before uploading photos of identifiable
                    people, and always for children. Photos are re-encoded and
                    location metadata is removed.
                </p>
            </CardHeader>
            <CardContent className="space-y-4">
                {media.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No photographs yet.
                    </p>
                ) : (
                    <ul className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        {media.map((item) => (
                            <li key={item.uuid} className="space-y-1">
                                <img
                                    src={item.thumbnail_url}
                                    alt={item.alt_text ?? ''}
                                    className="aspect-square w-full rounded-md border object-cover"
                                    loading="lazy"
                                />
                                <div className="flex flex-wrap items-center gap-1">
                                    <StatusBadge
                                        status={item.approval_status}
                                    />
                                    {item.visibility === 'internal' && (
                                        <StatusBadge
                                            status="inactive"
                                            label="Staff only"
                                        />
                                    )}
                                </div>
                                {item.caption && (
                                    <p className="text-xs text-muted-foreground">
                                        {item.caption}
                                    </p>
                                )}
                                {canManage && (
                                    <div className="flex gap-1">
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            aria-label="Make featured photo"
                                            onClick={() =>
                                                router.put(
                                                    update.url(item.uuid),
                                                    {
                                                        ...item,
                                                        is_featured: true,
                                                    },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Star
                                                className={
                                                    item.is_featured
                                                        ? 'fill-current'
                                                        : ''
                                                }
                                            />
                                        </Button>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            aria-label="Remove photo"
                                            onClick={() => {
                                                if (
                                                    window.confirm(
                                                        'Remove this photograph?',
                                                    )
                                                ) {
                                                    router.delete(
                                                        destroy.url(item.uuid),
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    );
                                                }
                                            }}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                {canManage && (
                    <form
                        className="grid gap-3 rounded-md border border-dashed p-3 sm:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.submit(store(), {
                                forceFormData: true,
                                preserveScroll: true,
                                onSuccess: () =>
                                    form.reset('image', 'caption', 'alt_text'),
                            });
                        }}
                    >
                        <FormField
                            label="Photo (JPEG, PNG or WebP)"
                            htmlFor="media-image"
                            error={form.errors.image}
                            className="sm:col-span-2"
                        >
                            <Input
                                id="media-image"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                onChange={(event) =>
                                    form.setData(
                                        'image',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Caption"
                            htmlFor="media-caption"
                            error={form.errors.caption}
                        >
                            <Input
                                id="media-caption"
                                value={form.data.caption}
                                onChange={(event) =>
                                    form.setData('caption', event.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Description for screen readers"
                            htmlFor="media-alt"
                            error={form.errors.alt_text}
                        >
                            <Input
                                id="media-alt"
                                value={form.data.alt_text}
                                onChange={(event) =>
                                    form.setData('alt_text', event.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Who can see it"
                            htmlFor="media-visibility"
                        >
                            <NativeSelect
                                id="media-visibility"
                                value={form.data.visibility}
                                onChange={(event) =>
                                    form.setData(
                                        'visibility',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="public">
                                    Public website (after approval)
                                </option>
                                <option value="internal">Staff only</option>
                            </NativeSelect>
                        </FormField>
                        <FormField
                            label="Consent"
                            htmlFor="media-consent"
                            error={form.errors.consent_status}
                        >
                            <NativeSelect
                                id="media-consent"
                                value={form.data.consent_status}
                                onChange={(event) =>
                                    form.setData(
                                        'consent_status',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="not_required">
                                    No identifiable people
                                </option>
                                <option value="obtained">
                                    Consent obtained
                                </option>
                                <option value="pending">Consent pending</option>
                                <option value="refused">Consent refused</option>
                            </NativeSelect>
                        </FormField>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.shows_identifiable_people}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'shows_identifiable_people',
                                        checked === true,
                                    )
                                }
                            />
                            Shows identifiable people
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.shows_children}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'shows_children',
                                        checked === true,
                                    )
                                }
                            />
                            Shows children
                        </label>
                        <div className="sm:col-span-2">
                            <Button
                                type="submit"
                                disabled={form.processing || !form.data.image}
                            >
                                <ImagePlus /> Upload
                            </Button>
                        </div>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}
