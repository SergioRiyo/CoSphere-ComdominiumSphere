export type IncidentOption = { value: string; label: string };
export type IncidentStatus = 'open' | 'in_progress' | 'completed' | 'canceled';
export type IncidentPriority = 'low' | 'medium' | 'high';
export type IncidentFilters = {
    type?: string | null;
    category?: string | null;
    status?: string | null;
    priority?: string | null;
    resident_id?: string | null;
    unit_id?: string | null;
    date_from?: string | null;
    date_to?: string | null;
};
export type IncidentOptions = {
    types: IncidentOption[];
    categories: IncidentOption[];
    statuses: IncidentOption[];
    priorities: IncidentOption[];
    residents?: IncidentOption[];
    units?: IncidentOption[];
};
export type IncidentSummary = {
    id: number;
    title: string;
    type: string;
    type_label: string;
    category: string | null;
    category_label: string;
    status: IncidentStatus;
    status_label: string;
    priority: IncidentPriority;
    priority_label: string;
    created_at: string;
    opened_at: string;
    attachments_count: number;
    maintenance_count: number;
    resident?: string;
    unit?: string;
};
export type IncidentListProps = {
    incidents: {
        data: IncidentSummary[];
        total: number;
        current_page: number;
        last_page: number;
    };
    filters: IncidentFilters;
    options: IncidentOptions;
    can_create?: boolean;
};
export type IncidentDetailsData = IncidentSummary & {
    description: string;
    attachments: { id: number; name: string; size: number }[];
    history: {
        key: string;
        kind: 'status' | 'priority';
        from_label: string | null;
        to_label: string;
        actor: string;
        reason: string | null;
        created_at: string;
    }[];
    maintenance_requests: {
        id: number;
        status: string;
        status_label: string;
        provider: string | null;
        created_at: string;
        scheduled_at: string | null;
        executed_at: string | null;
    }[];
    allowed_statuses: IncidentOption[];
    can_update_priority: boolean;
    can_create_maintenance: boolean;
};
