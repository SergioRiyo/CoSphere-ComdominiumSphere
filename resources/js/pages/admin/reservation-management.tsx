import ReservationList from '@/components/reservation-list';
import { dashboard } from '@/routes/admin';
import { index, manage } from '@/routes/admin/reservations';
import type { ReservationListProps } from '@/types/reservation';

export default function ReservationManagement(props: ReservationListProps) {
    return <ReservationList {...props} admin />;
}

ReservationManagement.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Reservas', href: index() },
        { title: 'Gerenciar reservas', href: manage() },
    ],
};
