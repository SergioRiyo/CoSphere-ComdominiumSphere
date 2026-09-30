import ReservationList from '@/components/reservation-list';
import { dashboard } from '@/routes/morador';
import { index } from '@/routes/morador/reservations';
import type { ReservationListProps } from '@/types/reservation';

export default function ReservationsPage(props: ReservationListProps) {
    return <ReservationList {...props} />;
}

ReservationsPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Minhas reservas', href: index() },
    ],
};
