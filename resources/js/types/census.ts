export type StatusCounts = {
    registered: number;
    submitted: number;
    verified: number;
    approved: number;
    rejected: number;
    draft: number;
};

export type CensusSummary = {
    occupants: StatusCounts;
    adults: number;
    children: number;
    age_unknown: number;
    households: StatusCounts;
    houses: {
        visited: number;
        approved: number;
        occupied: number;
        vacant: number;
        not_enumerated: number;
    };
    average_household_size: number | null;
    sex: Record<string, number>;
    age_groups: Record<string, number>;
};

export type QualityCheck = {
    key: string;
    label: string;
    count: number;
    severity: 'error' | 'warning' | 'info';
};

export type CampaignSummary = {
    id: number;
    reference_code: string;
    name: string;
    status: string;
    start_date: string;
    end_date: string;
    reference_date: string;
    closed_at: string | null;
    households_count: number | null;
    assignments_count: number | null;
};

export type BreakdownRow = {
    id: number;
    name: string;
    reference_code: string;
    is_enumerated: boolean;
    houses_visited: number;
    houses_approved: number;
    households: StatusCounts;
    occupants: StatusCounts;
    average_household_size: number | null;
};
