export type ReservationStatus =
    | 'pending'
    | 'confirmed'
    | 'cancelled'
    | 'rejected'
    | 'completed';

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
