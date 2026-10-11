import { useLiveQuery } from 'dexie-react-hooks';
import { ChevronRight, LocateFixed, Plus, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import { FormField } from '@/components/admin/form-field';
import { StatusBadge } from '@/components/admin/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import type { HouseFields, LocalHouse } from '@/offline/db';
import {
    FieldValidationError,
    createHouse,
    createHousehold,
    readHouse,
    readHousehold,
    saveVisit,
    updateHouse,
} from '@/offline/repository';
import type { FieldContext } from '@/offline/repository';
import { optionLabel, useField, useFieldContext } from './field-context';
import { SyncBadge } from './sync-badge';

const emptyHouse: HouseFields = {
    local_house_number: null,
    building_type: null,
    tenure: null,
    floors: null,
    latitude: null,
    longitude: null,
    gps_accuracy_m: null,
    notes: null,
};

export function HouseForm({
    blockId,
    ctx,
    house,
    initial,
    onDone,
}: {
    blockId: number;
    ctx: FieldContext;
    house?: LocalHouse;
    initial?: HouseFields;
    onDone: (house: LocalHouse | null) => void;
}) {
    const { pkg } = useField();
    const [fields, setFields] = useState<HouseFields>(initial ?? emptyHouse);
    const [error, setError] = useState<string | null>(null);
    const [locating, setLocating] = useState(false);

    const capturePosition = () => {
        if (!('geolocation' in navigator)) {
            setError('This device cannot provide a GPS position.');

            return;
        }

        setLocating(true);
        navigator.geolocation.getCurrentPosition(
            (position) => {
                setLocating(false);
                setFields((current) => ({
                    ...current,
                    latitude: Number(position.coords.latitude.toFixed(7)),
                    longitude: Number(position.coords.longitude.toFixed(7)),
                    gps_accuracy_m: Math.round(position.coords.accuracy),
                }));
            },
            () => {
                setLocating(false);
                setError(
                    'Could not read the GPS position. Location permission may be off. You can continue without it.',
                );
            },
            { enableHighAccuracy: true, timeout: 30_000, maximumAge: 0 },
        );
    };

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        setError(null);

        try {
            if (house) {
                await updateHouse(ctx, house, fields);
                onDone(house);
            } else {
                onDone(await createHouse(ctx, blockId, fields));
            }
        } catch (exception) {
            setError(
                exception instanceof FieldValidationError
                    ? exception.message
                    : 'The house could not be saved.',
            );
        }
    };

    return (
        <form onSubmit={submit} className="grid gap-3 sm:grid-cols-2">
            <FormField
                label="House number"
                htmlFor="house-number"
                hint="Leave blank if the house has no number."
            >
                <Input
                    id="house-number"
                    value={fields.local_house_number ?? ''}
                    maxLength={30}
                    onChange={(event) =>
                        setFields({
                            ...fields,
                            local_house_number: event.target.value || null,
                        })
                    }
                />
            </FormField>
            <FormField label="Type of building" htmlFor="building-type">
                <NativeSelect
                    id="building-type"
                    value={fields.building_type ?? ''}
                    onChange={(event) =>
                        setFields({
                            ...fields,
                            building_type: event.target.value || null,
                        })
                    }
                >
                    <option value="">Not recorded</option>
                    {pkg?.options.building_type?.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <FormField label="Tenure" htmlFor="tenure">
                <NativeSelect
                    id="tenure"
                    value={fields.tenure ?? ''}
                    onChange={(event) =>
                        setFields({
                            ...fields,
                            tenure: event.target.value || null,
                        })
                    }
                >
                    <option value="">Not recorded</option>
                    {pkg?.options.tenure?.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <FormField label="Floors" htmlFor="floors">
                <Input
                    id="floors"
                    type="number"
                    min={1}
                    max={60}
                    value={fields.floors ?? ''}
                    onChange={(event) =>
                        setFields({
                            ...fields,
                            floors: event.target.value
                                ? Number(event.target.value)
                                : null,
                        })
                    }
                />
            </FormField>
            <div className="space-y-1 sm:col-span-2">
                <Button
                    type="button"
                    variant="outline"
                    onClick={capturePosition}
                    disabled={locating}
                >
                    <LocateFixed />{' '}
                    {locating ? 'Reading GPS…' : 'Record GPS position'}
                </Button>
                <p className="text-xs text-muted-foreground">
                    {fields.latitude !== null
                        ? `Recorded (accuracy about ${fields.gps_accuracy_m ?? '?'} m). Stored encrypted and never shown publicly.`
                        : 'Optional. Never type in or estimate coordinates.'}
                </p>
            </div>
            <FormField
                label="Notes"
                htmlFor="house-notes"
                className="sm:col-span-2"
            >
                <Textarea
                    id="house-notes"
                    value={fields.notes ?? ''}
                    maxLength={2000}
                    onChange={(event) =>
                        setFields({
                            ...fields,
                            notes: event.target.value || null,
                        })
                    }
                />
            </FormField>
            <InputError
                message={error ?? undefined}
                className="sm:col-span-2"
            />
            <div className="flex gap-2 sm:col-span-2">
                <Button type="submit">Save house</Button>
                <Button
                    type="button"
                    variant="ghost"
                    onClick={() => onDone(null)}
                >
                    Cancel
                </Button>
            </div>
        </form>
    );
}

export function FieldHouse({ houseUuid }: { houseUuid: string }) {
    const { session, navigate, pkg } = useField();
    const ctx = useFieldContext();
    const [editing, setEditing] = useState(false);
    const [initial, setInitial] = useState<HouseFields | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [contacts, setContacts] = useState<Record<string, string | null>>({});

    const house = useLiveQuery(
        () => session.db.houses.get(houseUuid),
        [houseUuid],
    );
    const visit = useLiveQuery(
        () => session.db.visits.where('house_uuid').equals(houseUuid).first(),
        [houseUuid],
    );
    const households = useLiveQuery(
        () =>
            session.db.households
                .where('house_uuid')
                .equals(houseUuid)
                .toArray(),
        [houseUuid],
        [],
    );

    useEffect(() => {
        let cancelled = false;

        void Promise.all(
            households.map(
                async (household) =>
                    [
                        household.uuid,
                        (await readHousehold(ctx, household)).contact_name,
                    ] as const,
            ),
        ).then((entries) => {
            if (!cancelled) {
                setContacts(Object.fromEntries(entries));
            }
        });

        return () => {
            cancelled = true;
        };
    }, [households, ctx]);

    if (!house) {
        return (
            <p className="text-sm text-muted-foreground">
                House not found on this device.
            </p>
        );
    }

    const recordVisit = async (occupancy: string) => {
        setError(null);

        try {
            await saveVisit(ctx, house, {
                occupancy_status: occupancy,
                visited_at: new Date().toISOString(),
                notes: visit?.notes ?? null,
            });
        } catch (exception) {
            setError(
                exception instanceof FieldValidationError
                    ? exception.message
                    : 'The visit could not be saved.',
            );
        }
    };

    const addHousehold = async () => {
        setError(null);

        try {
            const household = await createHousehold(ctx, house, {
                contact_name: null,
                contact_phone: null,
                residence_type: 'usual',
            });
            navigate({ name: 'household', householdUuid: household.uuid });
        } catch (exception) {
            setError(
                exception instanceof FieldValidationError
                    ? exception.message
                    : 'The household could not be added.',
            );
        }
    };

    return (
        <div className="space-y-4">
            <button
                type="button"
                onClick={() =>
                    navigate({ name: 'block', blockId: house.block_id })
                }
                className="text-sm text-muted-foreground hover:underline"
            >
                ← Back to block
            </button>
            <div className="flex items-start justify-between gap-2">
                <div>
                    <h2 className="text-lg font-semibold">
                        House {house.local_house_number ?? '(no number)'}
                    </h2>
                    <p className="font-mono text-xs text-muted-foreground">
                        {house.reference_code ??
                            'Reference assigned when synced'}
                    </p>
                    <SyncBadge state={house.sync} />
                    {house.sync_message && (
                        <p className="text-xs text-red-700 dark:text-red-400">
                            {house.sync_message}
                        </p>
                    )}
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={async () => {
                        setInitial(await readHouse(ctx, house));
                        setEditing(true);
                    }}
                >
                    Edit
                </Button>
            </div>

            {editing && initial && (
                <Card>
                    <CardContent className="pt-6">
                        <HouseForm
                            blockId={house.block_id}
                            ctx={ctx}
                            house={house}
                            initial={initial}
                            onDone={() => setEditing(false)}
                        />
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Visit result</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                    <p className="text-sm">
                        {visit ? (
                            <>
                                Recorded as{' '}
                                <strong>
                                    {optionLabel(
                                        pkg,
                                        'occupancy_status',
                                        visit.occupancy_status,
                                    )}
                                </strong>{' '}
                                <StatusBadge status={visit.status} />
                            </>
                        ) : (
                            'Not recorded yet.'
                        )}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {pkg?.options.occupancy_status?.map((option) => (
                            <Button
                                key={option.value}
                                size="sm"
                                variant={
                                    visit?.occupancy_status === option.value
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() => void recordVisit(option.value)}
                            >
                                {option.label}
                            </Button>
                        ))}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="flex-row items-center justify-between">
                    <CardTitle className="flex items-center gap-2">
                        <Users className="size-4" /> Households (
                        {households.length})
                    </CardTitle>
                    <Button size="sm" onClick={() => void addHousehold()}>
                        <Plus /> Household
                    </Button>
                </CardHeader>
                <CardContent>
                    <InputError message={error ?? undefined} />
                    <ul className="divide-y rounded-md border">
                        {households.length === 0 && (
                            <li className="p-3 text-sm text-muted-foreground">
                                Several households may live in one house. Add
                                each one separately.
                            </li>
                        )}
                        {households.map((household, position) => (
                            <li key={household.uuid}>
                                <button
                                    type="button"
                                    onClick={() =>
                                        navigate({
                                            name: 'household',
                                            householdUuid: household.uuid,
                                        })
                                    }
                                    className="flex w-full items-center justify-between gap-2 px-3 py-3 text-left hover:bg-accent"
                                >
                                    <span>
                                        <span className="block font-medium">
                                            Household{' '}
                                            {household.sequence_in_house ??
                                                position + 1}
                                            {contacts[household.uuid] &&
                                                ` · ${contacts[household.uuid]}`}
                                        </span>
                                        <SyncBadge state={household.sync} />
                                    </span>
                                    <span className="flex items-center gap-2">
                                        <StatusBadge
                                            status={household.status}
                                        />
                                        <ChevronRight className="size-4" />
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                </CardContent>
            </Card>
        </div>
    );
}
