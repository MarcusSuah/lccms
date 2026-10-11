import 'leaflet/dist/leaflet.css';
import { latLngBounds } from 'leaflet';
import { useEffect } from 'react';
import {
    CircleMarker,
    MapContainer,
    Popup,
    TileLayer,
    Tooltip,
    useMap,
} from 'react-leaflet';
import type { MapPoint, MapTiles } from '@/types';

export type PointLayer = {
    key: string;
    label: string;
    color: string;
    points: MapPoint[];
};

/** Approximate centre of Liberia, used until there are points to fit. */
const DEFAULT_CENTER: [number, number] = [6.45, -9.43];
const DEFAULT_ZOOM = 7;

function FitToPoints({ layers }: { layers: PointLayer[] }) {
    const map = useMap();
    const signature = layers
        .map((layer) => `${layer.key}:${layer.points.length}`)
        .join('|');

    useEffect(() => {
        const coordinates = layers.flatMap((layer) =>
            layer.points.map(
                (point) => [point.lat, point.lng] as [number, number],
            ),
        );

        if (coordinates.length === 0) {
            return;
        }

        map.fitBounds(latLngBounds(coordinates), {
            padding: [32, 32],
            maxZoom: 15,
        });
        // Refit only when the set of visible points changes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [map, signature]);

    return null;
}

/**
 * Leaflet map of point layers. Markers are drawn as circles with a light
 * ring so overlapping points stay distinguishable; identity is also given by
 * the legend and each marker's tooltip, never by colour alone.
 *
 * Client-only: import it lazily so server rendering never loads Leaflet.
 */
export default function PointMap({
    tiles,
    layers,
    className,
}: {
    tiles: MapTiles;
    layers: PointLayer[];
    className?: string;
}) {
    return (
        <MapContainer
            center={DEFAULT_CENTER}
            zoom={DEFAULT_ZOOM}
            maxZoom={tiles.max_zoom}
            scrollWheelZoom
            className={className}
        >
            <TileLayer
                url={tiles.tile_url}
                attribution={tiles.attribution}
                maxZoom={tiles.max_zoom}
            />
            {layers.map((layer) =>
                layer.points.map((point) => (
                    <CircleMarker
                        key={`${layer.key}-${point.id}`}
                        center={[point.lat, point.lng]}
                        radius={6}
                        pathOptions={{
                            color: '#ffffff',
                            weight: 2,
                            fillColor: layer.color,
                            fillOpacity: 0.95,
                        }}
                    >
                        <Tooltip direction="top" offset={[0, -6]}>
                            <span className="font-medium">{point.label}</span>
                            <span className="block text-xs">{layer.label}</span>
                        </Tooltip>
                        <Popup>
                            <div className="space-y-0.5 text-sm">
                                <div className="font-medium">{point.label}</div>
                                {point.kind && <div>{point.kind}</div>}
                                {point.status && (
                                    <div className="text-muted-foreground">
                                        {point.status}
                                    </div>
                                )}
                                {point.url && (
                                    <a href={point.url} className="underline">
                                        Open record
                                    </a>
                                )}
                            </div>
                        </Popup>
                    </CircleMarker>
                )),
            )}
            <FitToPoints layers={layers} />
        </MapContainer>
    );
}
