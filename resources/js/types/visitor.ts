import type { PaginationLink } from './admin';

export type VisitorAuthorizationStatus =
    | 'pending_data'
    | 'active'
    | 'used'
    | 'expired'
    | 'canceled';

export type VisitorAuthorizationUnit = {
    block: string | null;
    number: string;
};

export type VisitorSummary = {
    name: string;
    cpf: string;
};

export type VisitorAuthorizationSummary = {
    id: number;
    visitor: VisitorSummary | null;
    unit: VisitorAuthorizationUnit;
    start_date: string;
    end_date: string;
    status: VisitorAuthorizationStatus;
    status_label: string;
};

export type VisitorAuthorizationDetails = Omit<
    VisitorAuthorizationSummary,
    'visitor'
> & {
    visitor: (VisitorSummary & { phone: string | null }) | null;
    vehicle_plate: string | null;
};

export type VisitorAuthorizationFilters = {
    search: string;
    status: VisitorAuthorizationStatus | '';
    date_from: string;
    date_to: string;
};

export type VisitorAuthorizationStatusOption = {
    value: VisitorAuthorizationStatus;
    label: string;
};

export type PaginatedVisitorAuthorizations = {
    data: VisitorAuthorizationSummary[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
    prev_page_url: string | null;
    next_page_url: string | null;
};
