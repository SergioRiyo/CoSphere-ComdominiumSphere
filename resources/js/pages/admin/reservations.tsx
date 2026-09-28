import ReservationList from '@/components/reservation-list';
import { dashboard } from '@/routes/admin';
import { index } from '@/routes/admin/reservations';
import type { PaginatedReservations } from '@/types/reservation';

export default function ReservationsPage({
    reservations,
}: {
    reservations: PaginatedReservations;
}) {
    return <ReservationList reservations={reservations} admin />;
}

ReservationsPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Reservas', href: index() },
    ],
};
