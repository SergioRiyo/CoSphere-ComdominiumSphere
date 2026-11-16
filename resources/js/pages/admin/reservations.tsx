import ReservationCalendar from '@/components/reservation-calendar';
import { dashboard } from '@/routes/admin';
import { index } from '@/routes/admin/reservations';

export default function ReservationsPage() {
    return <ReservationCalendar admin />;
}

ReservationsPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Reservas', href: index() },
    ],
};
