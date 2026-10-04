export type OrderHistoryFilters = {
    status: string;
    date_from: string;
    date_to: string;
    search: string;
    unit_id: number | null;
};

export type OrderHistoryEntry = {
    id: number;
    description: string | null;
    carrier: string | null;
    sender: string | null;
    tracking_code: string | null;
    status: string;
    status_label: string;
    created_at: string | null;
    received_at: string | null;
    picked_up_at: string | null;
    status_date: string | null;
    unit: { id: number; block: string | null; number: string };
    resident_name: string | null;
    received_by: string | null;
    pickup_confirmed_by: string | null;
    available_for_pickup: boolean;
    can_pickup: boolean;
};

export type OrderHistoryPagination = {
    data: OrderHistoryEntry[];
    current_page: number;
    last_page: number;
    total: number;
};
