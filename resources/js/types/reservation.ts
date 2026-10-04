export type ReservationStatus =
    | 'pending'
    | 'confirmed'
    | 'cancelled'
    | 'rejected';

export type OperationalReservation = {
    id: number;
    area: string;
    date: string;
    start: string;
    end: string;
    status: ReservationStatus;
    status_label: string;
    can_cancel: boolean;
    resident?: string;
    unit?: { block: string | null; number: string };
};

export type PaginatedReservations = {
    data: OperationalReservation[];
    current_page: number;
    last_page: number;
    total: number;
};

export type ReservationFilters = {
    status?: ReservationStatus | '' | null;
    date_from?: string | null;
    date_to?: string | null;
};

export type ReservationListProps = {
    reservations: PaginatedReservations;
    filters: ReservationFilters;
    statuses: { value: ReservationStatus; label: string }[];
};

export type ReservationDetails = OperationalReservation & {
    rejection_reason: string | null;
    history: {
        id: number;
        from_status: ReservationStatus | null;
        from_label: string | null;
        to_status: ReservationStatus;
        to_label: string;
        actor: string;
        reason: string | null;
        created_at: string;
    }[];
};
