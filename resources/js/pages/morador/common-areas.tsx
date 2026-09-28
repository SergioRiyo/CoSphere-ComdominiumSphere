import { Head, useHttp } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { dashboard } from '@/routes/morador';
import { availability, index } from '@/routes/morador/common-areas';
import { store } from '@/routes/morador/reservations';
import type {
    AvailabilityPeriod,
    CommonArea,
    CommonAreaAvailability,
    ReservationConfirmation,
    ReservationRequest,
} from '@/types/common-area';

export default function CommonAreasPage({
    areas,
    today,
}: {
    areas: Pick<CommonArea, 'id' | 'name'>[];
    today: string;
}) {
    const [areaId, setAreaId] = useState('');
    const [date, setDate] = useState(today);
    const [result, setResult] = useState<CommonAreaAvailability | null>(null);
    const [failed, setFailed] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const requestNumber = useRef(0);
    const http = useHttp<Record<string, never>, CommonAreaAvailability>({});

    async function consult(nextArea: string, nextDate: string) {
        const currentRequest = ++requestNumber.current;
        http.cancel();
        setResult(null);
        setFailed(false);
        setIsLoading(Boolean(nextArea && nextDate));

        if (!nextArea || !nextDate) {
            return;
        }

        try {
            const response = await http.get(
                availability.url(Number(nextArea), {
                    query: { date: nextDate },
                }),
            );

            if (currentRequest === requestNumber.current) {
                setResult(response);
            }
        } catch {
            if (currentRequest === requestNumber.current) {
                setFailed(true);
                toast.error(
                    'Não foi possível consultar a disponibilidade. Tente novamente.',
                );
            }
        } finally {
            if (currentRequest === requestNumber.current) {
                setIsLoading(false);
            }
        }
    }

    return (
        <>
            <Head title="Áreas comuns" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="grid gap-2">
                    <Building2 className="size-6 text-primary" />
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Áreas comuns
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Consulte as regras e a disponibilidade antes de planejar
                        sua reserva.
                    </p>
                </header>
                {areas.length === 0 ? (
                    <Card>
                        <CardContent className="py-10 text-center">
                            Nenhuma área disponível para reserva.
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <Card>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="grid min-w-0 gap-2">
                                    <Label htmlFor="common-area">Área</Label>
                                    <Select
                                        disabled={isSubmitting}
                                        value={areaId}
                                        onValueChange={(value) => {
                                            setAreaId(value);
                                            void consult(value, date);
                                        }}
                                    >
                                        <SelectTrigger
                                            id="common-area"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Selecione uma área" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {areas.map((area) => (
                                                <SelectItem
                                                    key={area.id}
                                                    value={String(area.id)}
                                                >
                                                    {area.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid min-w-0 gap-2">
                                    <Label htmlFor="availability-date">
                                        Data
                                    </Label>
                                    <Input
                                        id="availability-date"
                                        disabled={isSubmitting}
                                        type="date"
                                        value={date}
                                        onChange={(event) => {
                                            setDate(event.target.value);
                                            void consult(
                                                areaId,
                                                event.target.value,
                                            );
                                        }}
                                    />
                                </div>
                            </CardContent>
                        </Card>
                        <div aria-live="polite" aria-busy={isLoading}>
                            {isLoading ? (
                                <div className="grid gap-3" role="status">
                                    <span>Consultando disponibilidade…</span>
                                    <Skeleton className="h-32 w-full" />
                                    <Skeleton className="h-48 w-full" />
                                </div>
                            ) : failed ? (
                                <Card>
                                    <CardContent className="grid justify-items-start gap-3">
                                        <p role="alert">
                                            Não foi possível consultar a
                                            disponibilidade. Tente novamente.
                                        </p>
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                void consult(areaId, date)
                                            }
                                        >
                                            Tentar novamente
                                        </Button>
                                    </CardContent>
                                </Card>
                            ) : result ? (
                                <AvailabilityDetails result={result} />
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Selecione uma área e uma data para
                                    consultar.
                                </p>
                            )}
                        </div>
                        {areaId && date && (
                            <ReservationForm
                                key={`${areaId}-${date}`}
                                areaId={Number(areaId)}
                                date={date}
                                disabled={isLoading || failed || !result}
                                onProcessingChange={setIsSubmitting}
                                refresh={() => consult(areaId, date)}
                            />
                        )}
                    </>
                )}
            </div>
        </>
    );
}

function ReservationForm({
    areaId,
    date,
    disabled,
    onProcessingChange,
    refresh,
}: {
    areaId: number;
    date: string;
    disabled: boolean;
    onProcessingChange: (processing: boolean) => void;
    refresh: () => Promise<void>;
}) {
    const form = useHttp<ReservationRequest, ReservationConfirmation>({
        common_area_id: areaId,
        starts_at: '',
        ends_at: '',
    });
    const [confirmation, setConfirmation] =
        useState<ReservationConfirmation | null>(null);
    const [failure, setFailure] = useState<string | null>(null);
    const submitting = useRef(false);

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (disabled || submitting.current) {
            return;
        }

        submitting.current = true;
        onProcessingChange(true);
        setConfirmation(null);
        setFailure(null);
        let validationFailed = false;
        form.transform((data) => ({
            ...data,
            starts_at: `${date} ${data.starts_at}`,
            ends_at: `${date} ${data.ends_at}`,
        }));

        try {
            const response = await form.post(store.url(), {
                onError: () => {
                    validationFailed = true;
                },
            });
            setConfirmation(response);
            form.setData({
                common_area_id: areaId,
                starts_at: '',
                ends_at: '',
            });
            toast.success(
                response.status === 'pending'
                    ? 'Reserva solicitada. Aguardando aprovação.'
                    : 'Reserva confirmada.',
            );
            await refresh();
        } catch {
            if (validationFailed) {
                await refresh();
            } else {
                setFailure(
                    'Não foi possível confirmar o resultado da solicitação. Atualize a disponibilidade antes de tentar novamente.',
                );
                await refresh();
            }
        } finally {
            submitting.current = false;
            onProcessingChange(false);
        }
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Solicitar reserva</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4">
                <p className="text-sm text-muted-foreground">
                    Informe os horários na data selecionada. Períodos já
                    encerrados não podem ser solicitados. A disponibilidade será
                    verificada novamente ao enviar.
                </p>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <div className="grid min-w-0 gap-2">
                        <Label htmlFor="reservation-start">Início</Label>
                        <Input
                            id="reservation-start"
                            type="time"
                            step="1"
                            required
                            value={form.data.starts_at}
                            disabled={form.processing}
                            onChange={(event) =>
                                form.setData('starts_at', event.target.value)
                            }
                        />
                    </div>
                    <div className="grid min-w-0 gap-2">
                        <Label htmlFor="reservation-end">Fim</Label>
                        <Input
                            id="reservation-end"
                            type="time"
                            step="1"
                            required
                            value={form.data.ends_at}
                            disabled={form.processing}
                            onChange={(event) =>
                                form.setData('ends_at', event.target.value)
                            }
                        />
                    </div>
                    {form.hasErrors && (
                        <ul
                            role="alert"
                            className="grid gap-1 text-sm text-destructive sm:col-span-2"
                        >
                            {Object.entries(form.errors).map(
                                ([field, message]) => (
                                    <li key={field}>{message}</li>
                                ),
                            )}
                        </ul>
                    )}
                    {failure && (
                        <p
                            role="alert"
                            className="text-sm text-destructive sm:col-span-2"
                        >
                            {failure}
                        </p>
                    )}
                    <Button
                        type="submit"
                        disabled={disabled || form.processing}
                        className="sm:col-span-2 sm:justify-self-start"
                    >
                        {form.processing
                            ? 'Enviando solicitação…'
                            : 'Solicitar reserva'}
                    </Button>
                </form>
                {confirmation && (
                    <div
                        role="status"
                        className="grid gap-1 rounded-md border bg-muted/30 p-4 text-sm"
                    >
                        <p className="font-medium">
                            {confirmation.status === 'pending'
                                ? 'Reserva solicitada. Aguardando aprovação.'
                                : 'Reserva confirmada.'}
                        </p>
                        <p>
                            {confirmation.area} ·{' '}
                            {confirmation.date.split('-').reverse().join('/')} ·{' '}
                            {time(confirmation.start)}–{time(confirmation.end)}
                        </p>
                        <p>Status: {confirmation.status_label}</p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function AvailabilityDetails({ result }: { result: CommonAreaAvailability }) {
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

CommonAreasPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Áreas comuns', href: index() },
    ],
};
