import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type {
    AvailabilityPeriod,
    CommonAreaAvailability,
} from '@/types/common-area';

export default function CommonAreaAvailabilityDetails({
    result,
}: {
    result: CommonAreaAvailability;
}) {
    const { area } = result;
    const duration = area.max_reservation_minutes;

    return (
        <div className="grid items-start gap-6 lg:grid-cols-2">
            <Card className="min-w-0">
                <CardHeader>
                    <CardTitle className="break-words">{area.name}</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 text-sm">
                    {area.description && (
                        <p className="break-words whitespace-pre-wrap">
                            {area.description}
                        </p>
                    )}
                    <dl className="grid gap-3">
                        <div>
                            <dt className="text-muted-foreground">
                                Funcionamento
                            </dt>
                            <dd>
                                {area.available_from
                                    ? `Abertura: ${time(area.available_from)}`
                                    : 'Sem limite de abertura configurado'}{' '}
                                ·{' '}
                                {area.available_until
                                    ? `Fechamento: ${time(area.available_until)}`
                                    : 'Sem limite de fechamento configurado'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Duração máxima
                            </dt>
                            <dd>
                                {duration % 60 === 0
                                    ? `${duration / 60} ${duration === 60 ? 'hora' : 'horas'}`
                                    : `${duration} minutos`}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Aprovação</dt>
                            <dd>
                                {area.requires_approval
                                    ? 'Requer aprovação do administrador'
                                    : 'Aprovação automática'}
                            </dd>
                        </div>
                    </dl>
                    <div className="grid gap-2">
                        <h2 className="font-medium">Regras de utilização</h2>
                        <p className="break-words whitespace-pre-wrap">
                            {area.rules}
                        </p>
                    </div>
                </CardContent>
            </Card>
            <Card className="min-w-0">
                <CardHeader>
                    <CardTitle>
                        Disponibilidade de{' '}
                        {result.date.split('-').reverse().join('/')}
                    </CardTitle>
                </CardHeader>
                <CardContent className="grid gap-5 text-sm">
                    {result.occupied_periods.length === 0 && (
                        <p>
                            Nenhuma reserva ocupa este dia dentro do
                            funcionamento.
                        </p>
                    )}
                    {result.free_periods.length === 0 && (
                        <p className="font-medium">
                            Não há disponibilidade nesta data.
                        </p>
                    )}
                    <Periods title="Livre" periods={result.free_periods} />
                    <Periods
                        title="Indisponível"
                        periods={result.blocked_periods}
                    />
                    <Periods
                        title="Ocupado"
                        periods={result.occupied_periods}
                    />
                    <p className="text-muted-foreground">
                        Os períodos livres respeitam o funcionamento. Cada
                        reserva deve respeitar a duração máxima e começar e
                        terminar no mesmo dia. A disponibilidade pode mudar até
                        a solicitação.
                    </p>
                </CardContent>
            </Card>
        </div>
    );
}

function Periods({
    title,
    periods,
}: {
    title: string;
    periods: AvailabilityPeriod[];
}) {
    if (!periods.length) {
        return null;
    }

    return (
        <section className="grid gap-2">
            <h2 className="font-medium">{title}</h2>
            <ul className="grid gap-2">
                {periods.map((period, position) => (
                    <li
                        key={position}
                        className="flex flex-wrap justify-between gap-2 rounded-md border bg-muted/30 p-3"
                    >
                        <span>
                            {period.start === null
                                ? 'Início do dia'
                                : time(period.start)}{' '}
                            →{' '}
                            {period.end === null
                                ? 'Fim do dia'
                                : time(period.end)}
                        </span>
                        <span className="font-medium">{title}</span>
                    </li>
                ))}
            </ul>
        </section>
    );
}

function time(value: string) {
    return value.endsWith(':00') && value.length === 8
        ? value.slice(0, 5)
        : value;
}
