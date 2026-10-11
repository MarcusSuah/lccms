import { useLiveQuery } from 'dexie-react-hooks';
import { Pencil, Plus, Send, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { FormField } from '@/components/admin/form-field';
import { StatusBadge } from '@/components/admin/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import type {
    HouseholdFields,
    LocalHousehold,
    LocalOccupant,
    OccupantFields,
} from '@/offline/db';
import {
    FieldValidationError,
    createOccupant,
    isEditable,
    readHousehold,
    readOccupant,
    removeOccupant,
    submitHousehold,
    updateHousehold,
    updateOccupant,
} from '@/offline/repository';
import { optionLabel, useField, useFieldContext } from './field-context';
import { SyncBadge } from './sync-badge';

const emptyPerson: OccupantFields = {
    full_name: '',
    date_of_birth: null,
    age_years: null,
    age_is_estimated: false,
    sex: '',
    relationship_to_head: '',
    usual_resident: true,
    school_attendance: null,
};

function validatePerson(fields: OccupantFields): string | null {
    if (fields.full_name.trim().length < 2) return 'Enter the full name.';
    if (!fields.sex) return 'Select sex.';
    if (!fields.relationship_to_head)
        return 'Select the relationship to the head.';
    if (!fields.date_of_birth && fields.age_years === null)
        return 'Enter a date of birth or an age.';
    if (
        fields.age_years !== null &&
        (fields.age_years < 0 || fields.age_years > 130)
    )
        return 'Age must be between 0 and 130.';
    if (
        fields.date_of_birth &&
        fields.date_of_birth > new Date().toISOString().slice(0, 10)
    ) {
        return 'The date of birth is in the future.';
    }

    return null;
}

function OccupantForm({
    household,
    occupant,
    onDone,
}: {
    household: LocalHousehold;
    occupant?: LocalOccupant;
    onDone: () => void;
}) {
    const { pkg } = useField();
    const ctx = useFieldContext();
    const [fields, setFields] = useState<OccupantFields>(emptyPerson);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (occupant) {
            void readOccupant(ctx, occupant).then(setFields);
        }
    }, [occupant, ctx]);

    const set = <K extends keyof OccupantFields>(
        key: K,
        value: OccupantFields[K],
    ) => setFields((current) => ({ ...current, [key]: value }));

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        const problem = validatePerson(fields);

        if (problem) {
            setError(problem);

            return;
        }

        try {
            if (occupant) {
                await updateOccupant(ctx, household, occupant, {
                    ...fields,
                    full_name: fields.full_name.trim(),
                });
            } else {
                await createOccupant(ctx, household, {
                    ...fields,
                    full_name: fields.full_name.trim(),
                });
            }

            onDone();
        } catch (exception) {
            setError(
                exception instanceof FieldValidationError
                    ? exception.message
                    : 'The person could not be saved.',
            );
        }
    };

    return (
        <form onSubmit={submit} className="grid gap-3 sm:grid-cols-2">
            <FormField
                label="Full name"
                htmlFor="person-name"
                required
                className="sm:col-span-2"
            >
                <Input
                    id="person-name"
                    value={fields.full_name}
                    maxLength={255}
                    onChange={(event) => set('full_name', event.target.value)}
                    autoComplete="off"
                />
            </FormField>
            <FormField
                label="Relationship to head"
                htmlFor="person-relationship"
                required
            >
                <NativeSelect
                    id="person-relationship"
                    value={fields.relationship_to_head}
                    onChange={(event) =>
                        set('relationship_to_head', event.target.value)
                    }
                >
                    <option value="">Select…</option>
                    {pkg?.options.relationship_to_head?.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <FormField label="Sex" htmlFor="person-sex" required>
                <NativeSelect
                    id="person-sex"
                    value={fields.sex}
                    onChange={(event) => set('sex', event.target.value)}
                >
                    <option value="">Select…</option>
                    {pkg?.options.sex?.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <FormField
                label="Date of birth"
                htmlFor="person-dob"
                hint="If unknown, enter the age instead."
            >
                <Input
                    id="person-dob"
                    type="date"
                    value={fields.date_of_birth ?? ''}
                    onChange={(event) =>
                        set('date_of_birth', event.target.value || null)
                    }
                />
            </FormField>
            <FormField label="Age in completed years" htmlFor="person-age">
                <Input
                    id="person-age"
                    type="number"
                    inputMode="numeric"
                    min={0}
                    max={130}
                    value={fields.age_years ?? ''}
                    onChange={(event) =>
                        set(
                            'age_years',
                            event.target.value === ''
                                ? null
                                : Number(event.target.value),
                        )
                    }
                />
            </FormField>
            <div className="flex items-center gap-2">
                <Checkbox
                    id="person-estimated"
                    checked={fields.age_is_estimated}
                    onCheckedChange={(checked) =>
                        set('age_is_estimated', checked === true)
                    }
                />
                <Label htmlFor="person-estimated">Age is an estimate</Label>
            </div>
            <div className="flex items-center gap-2">
                <Checkbox
                    id="person-resident"
                    checked={fields.usual_resident}
                    onCheckedChange={(checked) =>
                        set('usual_resident', checked === true)
                    }
                />
                <Label htmlFor="person-resident">Usually lives here</Label>
            </div>
            <FormField
                label="School attendance"
                htmlFor="person-school"
                className="sm:col-span-2"
            >
                <NativeSelect
                    id="person-school"
                    value={fields.school_attendance ?? ''}
                    onChange={(event) =>
                        set('school_attendance', event.target.value || null)
                    }
                >
                    <option value="">Not recorded</option>
                    {pkg?.options.school_attendance?.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <InputError
                message={error ?? undefined}
                className="sm:col-span-2"
            />
            <div className="flex gap-2 sm:col-span-2">
                <Button type="submit">
                    {occupant ? 'Save changes' : 'Add person'}
                </Button>
                <Button type="button" variant="ghost" onClick={onDone}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}

type DecryptedPerson = { occupant: LocalOccupant; fields: OccupantFields };

export function FieldHousehold({ householdUuid }: { householdUuid: string }) {
    const { session, navigate, pkg } = useField();
    const ctx = useFieldContext();
    const [editing, setEditing] = useState<LocalOccupant | 'new' | null>(null);
    const [contact, setContact] = useState<HouseholdFields | null>(null);
    const [people, setPeople] = useState<DecryptedPerson[]>([]);
    const [error, setError] = useState<string | null>(null);

    const household = useLiveQuery(
        () => session.db.households.get(householdUuid),
        [householdUuid],
    );
    const occupants = useLiveQuery(
        () =>
            session.db.occupants
                .where('household_uuid')
                .equals(householdUuid)
                .filter((occupant) => occupant.status !== 'archived')
                .toArray(),
        [householdUuid],
        [],
    );

    useEffect(() => {
        if (household) {
            void readHousehold(ctx, household).then(setContact);
        }
    }, [household, ctx]);

    useEffect(() => {
        let cancelled = false;

        void Promise.all(
            occupants.map(async (occupant) => ({
                occupant,
                fields: await readOccupant(ctx, occupant),
            })),
        ).then((rows) => {
            if (!cancelled) {
                setPeople(
                    rows.sort(
                        (a, b) =>
                            Number(b.fields.relationship_to_head === 'head') -
                            Number(a.fields.relationship_to_head === 'head'),
                    ),
                );
            }
        });

        return () => {
            cancelled = true;
        };
    }, [occupants, ctx]);

    if (!household) {
        return (
            <p className="text-sm text-muted-foreground">
                Household not found on this device.
            </p>
        );
    }

    const editable = isEditable(household.status);

    const saveContact = async () => {
        if (!contact) return;
        setError(null);

        try {
            await updateHousehold(ctx, household, contact);
        } catch (exception) {
            setError(
                exception instanceof FieldValidationError
                    ? exception.message
                    : 'Could not save.',
            );
        }
    };

    const submit = async () => {
        setError(null);

        try {
            await submitHousehold(ctx, household);
        } catch (exception) {
            setError(
                exception instanceof FieldValidationError
                    ? exception.message
                    : 'Could not submit.',
            );
        }
    };

    return (
        <div className="space-y-4">
            <button
                type="button"
                onClick={() =>
                    navigate({ name: 'house', houseUuid: household.house_uuid })
                }
                className="text-sm text-muted-foreground hover:underline"
            >
                ← Back to house
            </button>
            <div className="flex items-start justify-between gap-2">
                <div>
                    <h2 className="text-lg font-semibold">
                        Household {household.sequence_in_house ?? ''}
                    </h2>
                    <p className="font-mono text-xs text-muted-foreground">
                        {household.reference_code ??
                            'Reference assigned when synced'}
                    </p>
                    <div className="flex items-center gap-2">
                        <StatusBadge status={household.status} />
                        <SyncBadge state={household.sync} />
                    </div>
                </div>
                {editable && (
                    <Button onClick={() => void submit()}>
                        <Send /> Submit for review
                    </Button>
                )}
            </div>
            {household.review_note && household.status === 'rejected' && (
                <p className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-800 dark:bg-amber-950">
                    Returned by your supervisor: {household.review_note}
                </p>
            )}
            {household.sync_message && (
                <p className="text-sm text-red-700 dark:text-red-400">
                    {household.sync_message}
                </p>
            )}
            <InputError message={error ?? undefined} />

            {contact && (
                <Card>
                    <CardHeader>
                        <CardTitle>Household details</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-3">
                        <FormField label="Contact person" htmlFor="hh-contact">
                            <Input
                                id="hh-contact"
                                disabled={!editable}
                                value={contact.contact_name ?? ''}
                                onChange={(event) =>
                                    setContact({
                                        ...contact,
                                        contact_name:
                                            event.target.value || null,
                                    })
                                }
                            />
                        </FormField>
                        <FormField label="Contact phone" htmlFor="hh-phone">
                            <Input
                                id="hh-phone"
                                type="tel"
                                disabled={!editable}
                                value={contact.contact_phone ?? ''}
                                onChange={(event) =>
                                    setContact({
                                        ...contact,
                                        contact_phone:
                                            event.target.value || null,
                                    })
                                }
                            />
                        </FormField>
                        <FormField label="Residence" htmlFor="hh-residence">
                            <NativeSelect
                                id="hh-residence"
                                disabled={!editable}
                                value={contact.residence_type}
                                onChange={(event) =>
                                    setContact({
                                        ...contact,
                                        residence_type: event.target.value,
                                    })
                                }
                            >
                                {pkg?.options.residence_type?.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                        {editable && (
                            <div className="sm:col-span-3">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => void saveContact()}
                                >
                                    Save details
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader className="flex-row items-center justify-between">
                    <CardTitle>People ({people.length})</CardTitle>
                    {editable && (
                        <Button size="sm" onClick={() => setEditing('new')}>
                            <Plus /> Person
                        </Button>
                    )}
                </CardHeader>
                <CardContent className="space-y-3">
                    {editing && (
                        <div className="rounded-md border p-3">
                            <OccupantForm
                                household={household}
                                occupant={
                                    editing === 'new' ? undefined : editing
                                }
                                onDone={() => setEditing(null)}
                            />
                        </div>
                    )}
                    <ul className="divide-y rounded-md border">
                        {people.length === 0 && (
                            <li className="p-3 text-sm text-muted-foreground">
                                Add every person who usually lives in this
                                household, starting with the head.
                            </li>
                        )}
                        {people.map(({ occupant, fields }) => (
                            <li
                                key={occupant.uuid}
                                className="flex items-center justify-between gap-2 px-3 py-2"
                            >
                                <span>
                                    <span className="block font-medium">
                                        {fields.full_name}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {optionLabel(
                                            pkg,
                                            'relationship_to_head',
                                            fields.relationship_to_head,
                                        )}{' '}
                                        · {optionLabel(pkg, 'sex', fields.sex)}{' '}
                                        ·{' '}
                                        {fields.date_of_birth ??
                                            `${fields.age_years ?? '?'} years${fields.age_is_estimated ? ' (est.)' : ''}`}
                                    </span>
                                    <SyncBadge state={occupant.sync} />
                                    {occupant.sync_message && (
                                        <span className="block text-xs text-red-700 dark:text-red-400">
                                            {occupant.sync_message}
                                        </span>
                                    )}
                                </span>
                                {editable && (
                                    <span className="flex gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Edit ${fields.full_name}`}
                                            onClick={() => setEditing(occupant)}
                                        >
                                            <Pencil />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Remove ${fields.full_name}`}
                                            onClick={async () => {
                                                if (
                                                    window.confirm(
                                                        `Remove ${fields.full_name} from this household?`,
                                                    )
                                                ) {
                                                    await removeOccupant(
                                                        ctx,
                                                        household,
                                                        occupant,
                                                    );
                                                }
                                            }}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                </CardContent>
            </Card>
        </div>
    );
}
