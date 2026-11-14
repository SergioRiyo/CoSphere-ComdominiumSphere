import ReservationList from '@/components/reservation-list';
import { dashboard } from '@/routes/morador';
import { index, manage } from '@/routes/morador/reservations';
import type { ReservationListProps } from '@/types/reservation';

export default function ReservationManagement(props: ReservationListProps) {
    return <ReservationList {...props} />;
}

ReservationManagement.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Reservas', href: index() },
        { title: 'Minhas reservas', href: manage() },
    ],
};
