import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { PageHeader } from '@/components/admin/page-header';
import { GeoAreaPicker } from '@/components/geo/geo-area-picker';
import type { GeoSelection } from '@/components/geo/geo-area-picker';
import { ClientMap, LAYER_COLORS } from '@/components/maps/client-map';
import { Checkbox } from '@/components/ui/checkbox';
import { index, points as pointsRoute } from '@/routes/admin/map';
import type { MapPoint, MapTiles } from '@/types';

type LayerOption = { key: string; label: string };
type LayerState = {
    points: MapPoint[];
    truncated: boolean;
    loading: boolean;
    failed: boolean;
};

export default function StaffMap({
    layers,
    tiles,
}: {
    layers: LayerOption[];
    tiles: MapTiles;
}) {
    const [enabled, setEnabled] = useState<string[]>(
        layers
            .filter((layer) => layer.key !== 'houses')
            .map((layer) => layer.key),
    );
    const [area, setArea] = useState<GeoSelection | null>(null);
    const [data, setData] = useState<Record<string, LayerState>>({});

    useEffect(() => {
        const controller = new AbortController();

        for (const key of enabled) {
            setData((current) => ({
                ...current,
                [key]: {
                    points: current[key]?.points ?? [],
                    truncated: false,
                    loading: true,
                    failed: false,
                },
            }));

            const query: Record<string, string | number> = { layer: key };

            if (area) {
                query.level = area.level;
                query.area_id = area.id;
            }

            fetch(pointsRoute.url({ query }), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then(async (response) => {
                    if (!response.ok) {
                        throw new Error(String(response.status));
                    }

                    const body = (await response.json()) as {
                        points: MapPoint[];
                        truncated: boolean;
                    };
                    setData((current) => ({
                        ...current,
                        [key]: {
                            points: body.points,
                            truncated: body.truncated,
                            loading: false,
                            failed: false,
                        },
                    }));
                })
                .catch((error: unknown) => {
                    if (
                        error instanceof DOMException &&
                        error.name === 'AbortError'
                    ) {
                        return;
                    }

                    setData((current) => ({
                        ...current,
                        [key]: {
                            points: [],
                            truncated: false,
                            loading: false,
                            failed: true,
                        },
                    }));
                });
        }

        return () => controller.abort();
    }, [enabled, area]);

    const visible = layers
        .filter((layer) => enabled.includes(layer.key))
        .map((layer) => ({
            key: layer.key,
            label: layer.label,
            color: LAYER_COLORS[layer.key] ?? '#2a78d6',
            points: data[layer.key]?.points ?? [],
        }));

    return (
        <>
            <Head title="Map" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Map"
                    description="Locations recorded in the registry and the census, limited to your areas. Records without coordinates are not shown."
                />

                <fieldset className="flex flex-wrap items-center gap-x-5 gap-y-2">
                    <legend className="sr-only">Layers</legend>
                    {layers.map((layer) => {
                        const state = data[layer.key];
                        const isOn = enabled.includes(layer.key);

                        return (
                            <label
                                key={layer.key}
                                className="flex items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    checked={isOn}
                                    onCheckedChange={(checked) =>
                                        setEnabled((current) =>
                                            checked === true
                                                ? [...current, layer.key]
                                                : current.filter(
                                                      (key) =>
                                                          key !== layer.key,
                                                  ),
                                        )
                                    }
                                />
                                <span
                                    aria-hidden
                                    className="size-3 rounded-full ring-2 ring-background"
                                    style={{
                                        backgroundColor:
                                            LAYER_COLORS[layer.key],
                                    }}
                                />
                                {layer.label}
                                {isOn && state && (
                                    <span className="text-muted-foreground tabular-nums">
                                        {state.loading
                                            ? '…'
                                            : state.failed
                                              ? 'unavailable'
                                              : `${state.points.length.toLocaleString()}${state.truncated ? '+' : ''}`}
                                    </span>
                                )}
                            </label>
                        );
                    })}
                </fieldset>

                <GeoAreaPicker
                    maxLevel="community"
                    idPrefix="map-area"
                    className="lg:grid-cols-5"
                    onChange={setArea}
                />

                {Object.values(data).some((state) => state.truncated) && (
                    <p className="text-sm text-muted-foreground">
                        Some layers show only the first{' '}
                        {tiles.max_points.toLocaleString()} points. Choose an
                        area to see all of them.
                    </p>
                )}

                <ClientMap
                    tiles={tiles}
                    layers={visible}
                    className="h-[65vh] min-h-96 w-full"
                />
            </div>
        </>
    );
}

StaffMap.layout = {
    breadcrumbs: [{ title: 'Map', href: index() }],
};
