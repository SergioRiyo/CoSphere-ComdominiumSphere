import type { UserRole } from '@/types/auth';

export type ManagedUserUnit = {
    id: number;
    block: string;
    number: string;
};

export type ManagedUser = {
    id: number;
    name: string;
    email: string;
    cpf: string;
    phone: string;
    role: UserRole;
    role_label: string;
    is_active: boolean;
    unit: ManagedUserUnit | null;
};

export type UnitOption = ManagedUserUnit;

export type UserRoleOption = {
    value: UserRole;
    label: string;
};

export type UserFilters = {
    search: string;
    role: UserRole | '';
    status: 'active' | 'inactive' | '';
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginatedUsers = {
    data: ManagedUser[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
    prev_page_url: string | null;
    next_page_url: string | null;
};
