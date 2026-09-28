import ReservationList from '@/components/reservation-list';
import { dashboard } from '@/routes/morador';
import { index } from '@/routes/morador/reservations';
import type { PaginatedReservations } from '@/types/reservation';

export default function ReservationsPage({
    reservations,
}: {
    reservations: PaginatedReservations;
}) {
    return <ReservationList reservations={reservations} />;
}

ReservationsPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Minhas reservas', href: index() },
    ],
};
