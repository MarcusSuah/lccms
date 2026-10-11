import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { ClientMap, LAYER_COLORS } from '@/components/maps/client-map';
import { Checkbox } from '@/components/ui/checkbox';
import type { MapPoint, MapTiles } from '@/types';

const layerLabels = {
    communities: 'Communities',
    facilities: 'Public facilities',
} as const;

type LayerKey = keyof typeof layerLabels;

export default function PublicMap({
    points,
    tiles,
}: {
    points: Record<LayerKey, MapPoint[]>;
    tiles: MapTiles;
}) {
    const [enabled, setEnabled] = useState<LayerKey[]>([
        'communities',
        'facilities',
    ]);
    const keys = Object.keys(layerLabels) as LayerKey[];

    return (
        <>
            <Head title="Map" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 px-4 py-10">
                <div className="space-y-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Map
                    </h1>
                    <p className="max-w-3xl text-muted-foreground">
                        Participating communities and verified public
                        facilities. Homes and personal information are never
                        shown on this map.
                    </p>
                </div>

                <fieldset className="flex flex-wrap items-center gap-x-5 gap-y-2">
                    <legend className="sr-only">Layers</legend>
                    {keys.map((key) => (
                        <label
                            key={key}
                            className="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                checked={enabled.includes(key)}
                                onCheckedChange={(checked) =>
                                    setEnabled((current) =>
                                        checked === true
                                            ? [...current, key]
                                            : current.filter(
                                                  (item) => item !== key,
                                              ),
                                    )
                                }
                            />
                            <span
                                aria-hidden
                                className="size-3 rounded-full ring-2 ring-background"
                                style={{ backgroundColor: LAYER_COLORS[key] }}
                            />
                            {layerLabels[key]}
                            <span className="text-muted-foreground tabular-nums">
                                {points[key].length.toLocaleString()}
                            </span>
                        </label>
                    ))}
                </fieldset>

                <ClientMap
                    tiles={tiles}
                    layers={enabled.map((key) => ({
                        key,
                        label: layerLabels[key],
                        color: LAYER_COLORS[key],
                        points: points[key],
                    }))}
                    className="h-[65vh] min-h-96 w-full"
                />

                {keys.every((key) => points[key].length === 0) && (
                    <p className="text-sm text-muted-foreground">
                        No verified locations have been published yet.
                    </p>
                )}
            </div>
        </>
    );
}
