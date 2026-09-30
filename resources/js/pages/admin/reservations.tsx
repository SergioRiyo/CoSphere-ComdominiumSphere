import ReservationList from '@/components/reservation-list';
import { dashboard } from '@/routes/admin';
import { index } from '@/routes/admin/reservations';
import type { ReservationListProps } from '@/types/reservation';

export default function ReservationsPage(props: ReservationListProps) {
    return <ReservationList {...props} admin />;
}

ReservationsPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Reservas', href: index() },
    ],
};
