import ReservationCalendar from '@/components/reservation-calendar';
import { dashboard } from '@/routes/morador';
import { index } from '@/routes/morador/reservations';

export default function ReservationsPage() {
    return <ReservationCalendar />;
}

ReservationsPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Minhas reservas', href: index() },
    ],
};
