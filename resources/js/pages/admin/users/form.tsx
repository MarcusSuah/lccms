import { Head, Link, router, useForm } from '@inertiajs/react';
import { MailPlus, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { FormField } from '@/components/admin/form-field';
import { PageHeader } from '@/components/admin/page-header';
import { GeoAreaPicker } from '@/components/geo/geo-area-picker';
import type { GeoSelection } from '@/components/geo/geo-area-picker';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, invitation, store, update } from '@/routes/admin/users';
import type { GeoLevel, Option, StaffUser } from '@/types';

type ScopeRow = {
    level: GeoLevel;
    area_id: number;
    label: string;
    starts_on: string;
    ends_on: string;
};

type FormData = {
    name: string;
    email: string;
    phone: string;
    is_active: boolean;
    roles: string[];
    scopes: ScopeRow[];
};

export default function UserForm({
    user,
    roleOptions,
}: {
    user: StaffUser | null;
    roleOptions: Option[];
}) {
    const form = useForm<FormData>({
        name: user?.name ?? '',
        email: user?.email ?? '',
        phone: user?.phone ?? '',
        is_active: user?.is_active ?? true,
        roles: user?.roles?.map((role) => role.value) ?? [],
        scopes:
            user?.scopes?.map((scope) => ({
                level: scope.level,
                area_id: scope.area_id,
                label: scope.label,
                starts_on: scope.starts_on ?? '',
                ends_on: scope.ends_on ?? '',
            })) ?? [],
    });
    const [pending, setPending] = useState<GeoSelection | null>(null);
    const [pickerKey, setPickerKey] = useState(0);
    const errors = form.errors as Record<string, string | undefined>;

    const toggleRole = (role: string, checked: boolean) => {
        form.setData(
            'roles',
            checked
                ? [...form.data.roles, role]
                : form.data.roles.filter((value) => value !== role),
        );
    };

    const addScope = () => {
        if (!pending) {
            return;
        }

        form.setData('scopes', [
            ...form.data.scopes,
            {
                level: pending.level,
                area_id: pending.id,
                label: `${pending.level.charAt(0).toUpperCase()}${pending.level.slice(1)}: ${pending.label}`,
                starts_on: '',
                ends_on: '',
            },
        ]);
        setPending(null);
        setPickerKey((key) => key + 1);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            scopes: data.scopes.map((scope) => ({
                level: scope.level,
                area_id: scope.area_id,
                starts_on: scope.starts_on || null,
                ends_on: scope.ends_on || null,
            })),
        }));

        if (user) {
            form.submit(update(user.id));
        } else {
            form.submit(store());
        }
    };

    return (
        <>
            <Head title={user ? `Edit ${user.name}` : 'New staff account'} />
            <form
                onSubmit={submit}
                className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6"
            >
                <PageHeader
                    title={user ? `Edit ${user.name}` : 'New staff account'}
                    description={
                        user
                            ? 'Changes to roles and areas take effect on the next request.'
                            : 'The new member of staff receives an email link to set their password. Signing in through that link also verifies their email address.'
                    }
                    actions={
                        user && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        invitation.url(user.id),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <MailPlus /> Re-send set-up link
                            </Button>
                        )
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Account</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Full name"
                            htmlFor="name"
                            error={form.errors.name}
                            required
                        >
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                autoComplete="off"
                                required
                            />
                        </FormField>
                        <FormField
                            label="Email"
                            htmlFor="email"
                            error={form.errors.email}
                            required
                        >
                            <Input
                                id="email"
                                type="email"
                                value={form.data.email}
                                onChange={(event) =>
                                    form.setData('email', event.target.value)
                                }
                                autoComplete="off"
                                required
                            />
                        </FormField>
                        <FormField
                            label="Phone"
                            htmlFor="phone"
                            error={form.errors.phone}
                        >
                            <Input
                                id="phone"
                                value={form.data.phone}
                                onChange={(event) =>
                                    form.setData('phone', event.target.value)
                                }
                            />
                        </FormField>
                        {user && (
                            <div className="flex items-center gap-2 self-end pb-2">
                                <Checkbox
                                    id="is_active"
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'is_active',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="is_active">
                                    Account is active (unticking signs the user
                                    out and revokes device tokens)
                                </Label>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Roles</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <fieldset className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <legend className="sr-only">Roles</legend>
                            {roleOptions.map((role) => (
                                <label
                                    key={role.value}
                                    className="flex items-center gap-2 rounded-md border p-2 text-sm"
                                >
                                    <Checkbox
                                        checked={form.data.roles.includes(
                                            role.value,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggleRole(
                                                role.value,
                                                checked === true,
                                            )
                                        }
                                    />
                                    {role.label}
                                </label>
                            ))}
                        </fieldset>
                        <InputError
                            className="mt-2"
                            message={
                                form.errors.roles ??
                                Object.entries(errors).find(([key]) =>
                                    key.startsWith('roles.'),
                                )?.[1]
                            }
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Geographic areas</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            The account can only see and change records inside
                            these areas. Leave empty only for national roles.
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {form.data.scopes.length > 0 && (
                            <ul className="divide-y rounded-md border">
                                {form.data.scopes.map((scope, position) => (
                                    <li
                                        key={`${scope.level}-${scope.area_id}`}
                                        className="grid gap-2 p-3 sm:grid-cols-[1fr_auto_auto_auto] sm:items-end"
                                    >
                                        <div>
                                            <div className="text-sm font-medium">
                                                {scope.label}
                                            </div>
                                            <InputError
                                                message={
                                                    errors[
                                                        `scopes.${position}.area_id`
                                                    ]
                                                }
                                            />
                                        </div>
                                        <FormField
                                            label="From"
                                            htmlFor={`starts-${position}`}
                                        >
                                            <Input
                                                id={`starts-${position}`}
                                                type="date"
                                                value={scope.starts_on}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'scopes',
                                                        form.data.scopes.map(
                                                            (row, i) =>
                                                                i === position
                                                                    ? {
                                                                          ...row,
                                                                          starts_on:
                                                                              event
                                                                                  .target
                                                                                  .value,
                                                                      }
                                                                    : row,
                                                        ),
                                                    )
                                                }
                                            />
                                        </FormField>
                                        <FormField
                                            label="Until"
                                            htmlFor={`ends-${position}`}
                                            error={
                                                errors[
                                                    `scopes.${position}.ends_on`
                                                ]
                                            }
                                        >
                                            <Input
                                                id={`ends-${position}`}
                                                type="date"
                                                value={scope.ends_on}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'scopes',
                                                        form.data.scopes.map(
                                                            (row, i) =>
                                                                i === position
                                                                    ? {
                                                                          ...row,
                                                                          ends_on:
                                                                              event
                                                                                  .target
                                                                                  .value,
                                                                      }
                                                                    : row,
                                                        ),
                                                    )
                                                }
                                            />
                                        </FormField>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Remove ${scope.label}`}
                                            onClick={() =>
                                                form.setData(
                                                    'scopes',
                                                    form.data.scopes.filter(
                                                        (_, i) =>
                                                            i !== position,
                                                    ),
                                                )
                                            }
                                        >
                                            <Trash2 />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}

                        <div className="space-y-3 rounded-md border border-dashed p-3">
                            <GeoAreaPicker
                                key={pickerKey}
                                maxLevel="block"
                                onChange={setPending}
                                idPrefix="scope"
                            />
                            <Button
                                type="button"
                                variant="secondary"
                                disabled={!pending}
                                onClick={addScope}
                            >
                                <Plus /> Add{' '}
                                {pending ? pending.label : 'selected area'}
                            </Button>
                        </div>
                        <InputError message={form.errors.scopes} />
                    </CardContent>
                </Card>

                <div className="flex items-center justify-end gap-2">
                    <Button asChild variant="ghost">
                        <Link href={index()}>Cancel</Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {user ? 'Save changes' : 'Create and send invitation'}
                    </Button>
                </div>
            </form>
        </>
    );
}

UserForm.layout = {
    breadcrumbs: [{ title: 'Staff accounts', href: index() }],
};
