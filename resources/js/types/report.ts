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

export type ReportFilters = {
    access_date_from?: string;
    access_date_to?: string;
    access_situation?: string;
};

export type ReportOptions = {
    access_situations: ReportOption[];
};
