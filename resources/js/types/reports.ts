import type { GeoLevel, Option } from './models';

/** Which filter controls a report accepts; presence of a key enables it. */
export type ReportFilterSpec = {
    campaign?: unknown;
    area?: unknown;
    breakdown?: unknown;
    period?: unknown;
    status?: { label: string; options: Option[] };
};

export type ReportSummary = {
    key: string;
    title: string;
    description: string;
    category: string;
    personal_data: boolean;
    can_export: boolean;
    filters: ReportFilterSpec;
};

export type ReportColumn = {
    key: string;
    label: string;
    numeric: boolean;
};

export type ReportRow = Record<string, string | number | boolean | null>;

export type ReportFilters = {
    campaign?: number;
    level?: GeoLevel;
    area_id?: number;
    breakdown?: GeoLevel;
    from?: string;
    to?: string;
    status?: string;
};

export type ExportFormat = 'csv' | 'xlsx' | 'pdf';

export type ExportJobSummary = {
    uuid: string;
    report: string;
    report_title?: string;
    format: ExportFormat;
    status: 'queued' | 'running' | 'completed' | 'failed';
    classification: 'aggregate' | 'personal';
    row_count: number | null;
    error: string | null;
    created_at: string | null;
    expires_at: string | null;
    is_downloadable: boolean;
};

export type MapPoint = {
    id: number;
    lat: number;
    lng: number;
    label: string | null;
    kind: string | null;
    status: string | null;
    url: string | null;
};

export type MapTiles = {
    tile_url: string;
    attribution: string;
    max_zoom: number;
    max_points: number;
};
