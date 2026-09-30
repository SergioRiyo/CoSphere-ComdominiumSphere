import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as adminIndex } from '@/routes/admin/reservations';
import { index as residentIndex } from '@/routes/morador/reservations';
import type { ReservationDetails as Details } from '@/types/reservation';

export default function ReservationDetails({
    reservation,
    admin = false,
}: {
    reservation: Details;
    admin?: boolean;
}) {
    const date = (value: string) => value.split('-').reverse().join('/');

    return (
        <>
            <Head title="Detalhes da reserva" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold">
                        Detalhes da reserva
                    </h1>
                    <Button asChild variant="outline">
                        <Link href={admin ? adminIndex() : residentIndex()}>
                            Voltar às reservas
                        </Link>
                    </Button>
                </header>
                <Card>
                    <CardHeader className="flex flex-wrap items-start justify-between gap-3">
                        <CardTitle className="break-words">
                            {reservation.area}
                        </CardTitle>
                        <Badge variant="outline">
                            {reservation.status_label}
                        </Badge>
                    </CardHeader>
                    <CardContent className="grid gap-3 text-sm">
                        <p>
                            {date(reservation.date)} · {reservation.start} →{' '}
                            {reservation.end}
                        </p>
                        {admin && (
                            <div>
                                <p className="break-words">
                                    Morador: {reservation.resident}
                                </p>
                                <p>
                                    {reservation.unit?.block
                                        ? `Bloco ${reservation.unit.block} · `
                                        : ''}
                                    Unidade {reservation.unit?.number}
                                </p>
                            </div>
                        )}
                        {reservation.rejection_reason && (
                            <div>
                                <p className="font-medium">Motivo da recusa</p>
                                <p className="break-words whitespace-pre-wrap">
                                    {reservation.rejection_reason}
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Histórico de status</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {reservation.history.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Não há alterações detalhadas registradas para
                                esta reserva.
                            </p>
                        ) : (
                            <>
                                {reservation.history[0].from_status !==
                                    null && (
                                    <p className="mb-4 text-sm text-muted-foreground">
                                        O histórico disponível começa na
                                        primeira alteração registrada. Eventos
                                        anteriores não estão disponíveis.
                                    </p>
                                )}
                                <ol className="grid gap-5 border-l pl-5">
                                    {reservation.history.map((event) => (
                                        <li
                                            key={event.id}
                                            className="grid min-w-0 gap-1 text-sm"
                                        >
                                            <time
                                                className="text-muted-foreground"
                                                dateTime={event.created_at.replace(
                                                    ' ',
                                                    'T',
                                                )}
                                            >
                                                {date(
                                                    event.created_at.slice(
                                                        0,
                                                        10,
                                                    ),
                                                )}{' '}
                                                · {event.created_at.slice(11)}
                                            </time>
                                            <p className="font-medium">
                                                {event.from_status === null
                                                    ? `Reserva criada — ${event.to_label}`
                                                    : `${event.from_label} → ${event.to_label}`}
                                            </p>
                                            <p className="break-words">
                                                Por: {event.actor}
                                            </p>
                                            {event.reason && (
                                                <p className="break-words whitespace-pre-wrap">
                                                    Motivo: {event.reason}
                                                </p>
                                            )}
                                        </li>
                                    ))}
                                </ol>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
