import ReservationDetails from '@/components/reservation-details';
import { dashboard } from '@/routes/admin';
import { index } from '@/routes/admin/reservations';
import type { ReservationDetails as Details } from '@/types/reservation';

export default function ReservationDetailsPage({
    reservation,
}: {
    reservation: Details;
}) {
    return <ReservationDetails reservation={reservation} admin />;
}

ReservationDetailsPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Reservas', href: index() },
    ],
};
