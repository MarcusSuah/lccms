import { useEffect, useState } from 'react';
import { NativeSelect } from '@/components/ui/native-select';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { options as geoOptions } from '@/routes/admin/geography';
import type { GeoLevel } from '@/types';
import { GEO_LEVELS } from '@/types/models';

export type GeoSelection = {
    level: GeoLevel;
    id: number;
    label: string;
    /** Every selected ancestor, keyed by level. */
    path: Partial<Record<GeoLevel, number>>;
};

type AreaOption = { value: number; label: string; code: string };

const levelLabel = (level: GeoLevel) =>
    level.charAt(0).toUpperCase() + level.slice(1);

async function fetchOptions(
    level: GeoLevel,
    parentId: number | null,
    scope: 'assigned' | 'registry',
    optionsUrl: string | null,
): Promise<AreaOption[]> {
    const query: Record<string, string | number> = { level, scope };

    if (parentId !== null) {
        query.parent_id = parentId;
    }

    const url = optionsUrl
        ? `${optionsUrl}?${new URLSearchParams(Object.entries(query).map(([key, value]) => [key, String(value)])).toString()}`
        : geoOptions.url({ query });
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        return [];
    }

    const body = (await response.json()) as { data: AreaOption[] };

    return body.data;
}

/**
 * Cascading county → … → block selector. Options come from the server and are
 * limited to the user's assigned areas unless scope="registry".
 *
 * The selection is the deepest chosen level. With `exactLevel`, the parent
 * selects are shown but only a choice at `maxLevel` is reported.
 */
export function GeoAreaPicker({
    maxLevel,
    onChange,
    exactLevel = false,
    scope = 'assigned',
    idPrefix = 'geo',
    className,
    disabled = false,
    optionsUrl = null,
}: {
    maxLevel: GeoLevel;
    onChange: (selection: GeoSelection | null) => void;
    exactLevel?: boolean;
    scope?: 'assigned' | 'registry';
    idPrefix?: string;
    className?: string;
    disabled?: boolean;
    /** Alternative options endpoint, e.g. the public registry for guests. */
    optionsUrl?: string | null;
}) {
    const levels = GEO_LEVELS.slice(0, GEO_LEVELS.indexOf(maxLevel) + 1);
    const [selected, setSelected] = useState<(number | null)[]>(
        levels.map(() => null),
    );
    const [choices, setChoices] = useState<AreaOption[][]>(
        levels.map(() => []),
    );

    useEffect(() => {
        let cancelled = false;

        void fetchOptions('county', null, scope, optionsUrl).then(
            (counties) => {
                if (!cancelled) {
                    setChoices((current) => [counties, ...current.slice(1)]);
                }
            },
        );

        return () => {
            cancelled = true;
        };
    }, [scope, optionsUrl]);

    const choose = (depth: number, value: string) => {
        const id = value === '' ? null : Number(value);
        const nextSelected = [
            ...selected.slice(0, depth),
            id,
            ...levels.slice(depth + 1).map(() => null),
        ];
        setSelected(nextSelected);
        setChoices((current) => [
            ...current.slice(0, depth + 1),
            ...levels.slice(depth + 1).map(() => []),
        ]);

        const childLevel = levels[depth + 1];

        if (id !== null && childLevel) {
            void fetchOptions(childLevel, id, scope, optionsUrl).then(
                (children) => {
                    setChoices((current) => {
                        const next = [...current];
                        next[depth + 1] = children;

                        return next;
                    });
                },
            );
        }

        let deepest = -1;
        nextSelected.forEach((value, index) => {
            if (value !== null) {
                deepest = index;
            }
        });

        if (deepest < 0 || (exactLevel && deepest !== levels.length - 1)) {
            onChange(null);

            return;
        }

        const option = choices[deepest]?.find(
            (candidate) => candidate.value === nextSelected[deepest],
        );

        const path: Partial<Record<GeoLevel, number>> = {};
        nextSelected.forEach((value, index) => {
            if (value !== null) {
                path[levels[index]] = value;
            }
        });

        onChange({
            level: levels[deepest],
            id: nextSelected[deepest] as number,
            label: option ? `${option.label} (${option.code})` : '',
            path,
        });
    };

    return (
        <div
            className={cn(
                'grid gap-3 sm:grid-cols-2 lg:grid-cols-3',
                className,
            )}
        >
            {levels.map((level, depth) => {
                const isEnabled = depth === 0 || selected[depth - 1] !== null;

                return (
                    <div key={level} className="grid gap-1.5">
                        <Label htmlFor={`${idPrefix}-${level}`}>
                            {levelLabel(level)}
                        </Label>
                        <NativeSelect
                            id={`${idPrefix}-${level}`}
                            disabled={disabled || !isEnabled}
                            value={selected[depth] ?? ''}
                            onChange={(event) =>
                                choose(depth, event.target.value)
                            }
                        >
                            <option value="">
                                {isEnabled
                                    ? `Select ${level}…`
                                    : `Choose a ${levels[depth - 1]} first`}
                            </option>
                            {choices[depth].map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label} · {option.code}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                );
            })}
        </div>
    );
}
