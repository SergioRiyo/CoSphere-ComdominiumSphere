import ReservationDetails from '@/components/reservation-details';
import { dashboard } from '@/routes/morador';
import { index } from '@/routes/morador/reservations';
import type { ReservationDetails as Details } from '@/types/reservation';

export default function ReservationDetailsPage({
    reservation,
}: {
    reservation: Details;
}) {
    return <ReservationDetails reservation={reservation} />;
}

ReservationDetailsPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Minhas reservas', href: index() },
    ],
};
