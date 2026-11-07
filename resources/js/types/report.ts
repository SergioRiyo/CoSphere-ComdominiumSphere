export type ReportOption = {
    value: string;
    label: string;
};

export type ReportPaginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
};

export type ReservationReportItem = {
    area: string;
    resident: string;
    unit: string;
    starts_at: string;
    ends_at: string;
    status: string;
    status_label: string;
};

export type AccessReportItem = {
    visitor: string;
    unit: string;
    entry_at: string | null;
    exit_at: string | null;
    entry_doorman: string | null;
    exit_doorman: string | null;
    situation: string;
    situation_label: string;
};

export type IncidentReportItem = {
    category: string;
    category_label: string;
    title: string;
    unit: string;
    status: string;
    status_label: string;
    opened_at: string;
    updated_at: string;
};

export type ReportFilters = {
    reservation_date_from?: string;
    reservation_date_to?: string;
    reservation_status?: string;
    common_area_id?: string;
    access_date_from?: string;
    access_date_to?: string;
    access_situation?: string;
    incident_date_from?: string;
    incident_date_to?: string;
    incident_category?: string;
    incident_status?: string;
};

export type ReportOptions = {
    common_areas: ReportOption[];
    reservation_statuses: ReportOption[];
    access_situations: ReportOption[];
    incident_categories: ReportOption[];
    incident_statuses: ReportOption[];
};
