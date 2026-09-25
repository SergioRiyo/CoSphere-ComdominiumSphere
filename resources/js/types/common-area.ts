import type { PaginationLink } from '@/types/admin';

export type CommonAreaStatus = 'active' | 'inactive' | 'maintenance';

export type CommonArea = {
    id: number;
    name: string;
    description: string | null;
    available_from: string | null;
    available_until: string | null;
    max_reservation_minutes: number;
    rules: string;
    requires_approval: boolean;
    status: CommonAreaStatus;
};

export type PaginatedCommonAreas = {
    data: CommonArea[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
    prev_page_url: string | null;
    next_page_url: string | null;
};
