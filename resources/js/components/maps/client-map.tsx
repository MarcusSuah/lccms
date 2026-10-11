import { lazy, Suspense, useEffect, useState } from 'react';
import type { ComponentProps } from 'react';
import type PointMapComponent from '@/components/maps/point-map';
import { cn } from '@/lib/utils';

const PointMap = lazy(() => import('@/components/maps/point-map'));

/**
 * Renders the Leaflet map only in the browser, after mount, so server-side
 * rendering never touches `window`.
 */
export function ClientMap({
    className,
    ...props
}: ComponentProps<typeof PointMapComponent>) {
    const [isMounted, setIsMounted] = useState(false);

    useEffect(() => setIsMounted(true), []);

    const placeholder = (
        <div
            className={cn('animate-pulse rounded-lg bg-muted', className)}
            aria-label="Loading map"
        />
    );

    if (!isMounted) {
        return placeholder;
    }

    return (
        <Suspense fallback={placeholder}>
            <PointMap className={cn('z-0 rounded-lg', className)} {...props} />
        </Suspense>
    );
}

/** Fixed categorical order for map layers; colour follows the layer, never its rank. */
export const LAYER_COLORS: Record<string, string> = {
    communities: '#2a78d6',
    facilities: '#eb6834',
    infrastructure: '#1baf7a',
    houses: '#eda100',
};
