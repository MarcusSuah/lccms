export type Option<TValue extends string | number = string> = {
    value: TValue;
    label: string;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

/** Shape produced by a Laravel API resource collection over a paginator. */
export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        links: PaginationLink[];
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
};

/** Shape produced by a plain LengthAwarePaginator (e.g. ->through()). */
export type SimplePaginated<T> = {
    data: T[];
    current_page: number;
    from: number | null;
    last_page: number;
    links: PaginationLink[];
    per_page: number;
    to: number | null;
    total: number;
};

export type GeoLevel =
    | 'county'
    | 'district'
    | 'clan'
    | 'town'
    | 'community'
    | 'block';

export const GEO_LEVELS: GeoLevel[] = [
    'county',
    'district',
    'clan',
    'town',
    'community',
    'block',
];

export type RecordStatus = 'active' | 'inactive' | 'archived';

export type VerificationStatus = 'unverified' | 'verified' | 'rejected';

export type GeoBreadcrumb = {
    id: number;
    level: GeoLevel;
    name: string;
    reference_code: string;
};

export type GeoArea = {
    id: number;
    level: GeoLevel;
    reference_code: string;
    name: string;
    description: string | null;
    status: RecordStatus;
    verification_status: VerificationStatus;
    latitude: string | null;
    longitude: string | null;
    has_boundary: boolean;
    boundary?: string | null;
    is_fixture: boolean;
    district_number?: number | null;
    slug?: string;
    history?: string | null;
    public_email?: string | null;
    public_phone?: string | null;
    is_published?: boolean;
    children_count?: number;
    breadcrumb?: GeoBreadcrumb[];
    updated_at: string | null;
};

export type StaffUserScope = {
    id: number;
    level: GeoLevel;
    area_id: number;
    label: string;
    starts_on: string | null;
    ends_on: string | null;
};

export type StaffUser = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    is_active: boolean;
    email_verified: boolean;
    two_factor_enabled: boolean;
    last_login_at: string | null;
    roles?: Option[];
    scopes?: StaffUserScope[];
};
