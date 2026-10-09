import type { IncidentOption } from '@/types/incident';
export type MaintenanceHistoryEvent = {
    key: string;
    kind: string;
    at: string;
    actor: string;
    text: string;
    reason: string | null;
    changes: Record<
        string,
        {
            old: string | number | null;
            new: string | number | null;
            old_label?: string | null;
            new_label?: string | null;
        }
    >;
};
export type Maintenance = {
    id: number;
    unit_id: number;
    unit: string;
    incident_id: number | null;
    incident_title: string | null;
    admin_id: number | null;
    admin: string | null;
    service_provider_id: number | null;
    provider: string | null;
    provider_archived: boolean;
    description: string;
    status: string;
    status_label: string;
    scheduled_at: string | null;
    executed_at: string | null;
    created_at: string;
    cost: string | null;
    history: MaintenanceHistoryEvent[];
    can_edit: boolean;
    allowed_statuses: IncidentOption[];
};
export type MaintenanceOptions = {
    units: IncidentOption[];
    admins: IncidentOption[];
    providers: IncidentOption[];
    statuses: IncidentOption[];
};
export type Paged<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
};
export type Provider = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    specialty: string | null;
    cpf_cnpj: string | null;
    deleted_at: string | null;
};
