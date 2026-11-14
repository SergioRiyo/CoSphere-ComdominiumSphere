import { Head, Link, useHttp } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    calendar as adminCalendar,
    manage as adminManage,
    show as adminShow,
} from '@/routes/admin/reservations';
import {
    calendar as residentCalendar,
    manage as residentManage,
    show as residentShow,
    store,
} from '@/routes/morador/reservations';

type Area = {
    id: number;
    name: string;
    status: string;
    available_from: string | null;
    available_until: string | null;
    max_reservation_minutes: number | null;
    rules: string | null;
    requires_approval: boolean;
};
type Event = {
    id: number | null;
    area_id: number;
    area: string;
    start: string;
    end: string;
    status: string | null;
    status_label: string;
    resident?: string;
    unit?: string;
};
type Block = {
    area_id: number;
    start: string;
    end: string;
    maintenance: boolean;
};
type Data = {
    areas: Area[];
    reservations: Event[];
    blocks: Block[];
    now: string;
};
type View = 'month' | 'week' | 'day';
const dateKey = (date: Date) =>
    [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
const addDays = (date: Date, days: number) =>
    new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);
const clock = (value: number) =>
    String(Math.floor(value / 60)).padStart(2, '0') +
    ':' +
    String(value % 60).padStart(2, '0');
const minutes = (value: string | null, fallback: number) =>
    value
        ? Number(value.slice(0, 2)) * 60 + Number(value.slice(3, 5))
        : fallback;
const overlapsDay = (item: { start: string; end: string }, date: string) =>
    item.start < date + ' 24:00:00' && item.end > date + ' 00:00:00';
const formatDate = (value: string) =>
    new Date(value + 'T12:00:00').toLocaleDateString('pt-BR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

function availablePairs(
    area: Area | undefined,
    date: string,
    data: Data | null,
): [string, string][] {
    if (!area || area.status !== 'active' || !data) {
        return [];
    }

    const opening = minutes(area.available_from, 0);
    const closing = Math.min(minutes(area.available_until, 1440), 1439);
    const occupied = [
        ...data.reservations.filter(
            (item) =>
                item.area_id === area.id &&
                (item.status === null ||
                    ['pending', 'confirmed'].includes(item.status)),
        ),
        ...data.blocks.filter((item) => item.area_id === area.id),
    ];
    const pairs: [string, string][] = [];

    for (
        let first = Math.ceil(opening / 30) * 30;
        first + 30 <= closing;
        first += 30
    ) {
        for (
            let last = first + 30;
            last <= closing &&
            (!area.max_reservation_minutes ||
                last - first <= area.max_reservation_minutes);
            last += 30
        ) {
            const start = date + ' ' + clock(first) + ':00';
            const end = date + ' ' + clock(last) + ':00';

            if (
                start > data.now &&
                !occupied.some((item) => item.start < end && item.end > start)
            ) {
                pairs.push([clock(first), clock(last)]);
            }
        }
    }

    return pairs;
}

export default function ReservationCalendar({
    admin = false,
}: {
    admin?: boolean;
}) {
    const [view, setView] = useState<View>('day');
    const [cursor, setCursor] = useState(() => new Date());
    const [selected, setSelected] = useState(() => dateKey(new Date()));
    const [areaId, setAreaId] = useState('');
    const [data, setData] = useState<Data | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(false);
    const [revision, setRevision] = useState(0);
    const [modalOpen, setModalOpen] = useState(false);
    const [start, setStart] = useState('');
    const [end, setEnd] = useState('');
    const form = useHttp<
        { common_area_id: number; starts_at: string; ends_at: string },
        { status: string }
    >({ common_area_id: 0, starts_at: '', ends_at: '' });
    const days = useMemo(() => {
        if (view === 'day') {
            return [cursor];
        }

        const base =
            view === 'month'
                ? new Date(cursor.getFullYear(), cursor.getMonth(), 1)
                : cursor;
        const first = addDays(base, -base.getDay());

        return Array.from({ length: view === 'month' ? 42 : 7 }, (_, i) =>
            addDays(first, i),
        );
    }, [cursor, view]);
    const from = dateKey(days[0]);
    const to = dateKey(days[days.length - 1]);

    useEffect(() => {
        const controller = new AbortController();
        queueMicrotask(() => {
            if (!controller.signal.aborted) {
                setLoading(true);
                setError(false);
            }
        });
        const endpoint = admin ? adminCalendar : residentCalendar;
        fetch(
            endpoint.url({
                query: {
                    from,
                    to,
                    ...(areaId ? { area: Number(areaId) } : {}),
                },
            }),
            {
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            },
        )
            .then((response) => {
                if (!response.ok) {
                    throw new Error();
                }

                return response.json() as Promise<Data>;
            })
            .then(setData)
            .catch((exception: Error) => {
                if (exception.name !== 'AbortError') {
                    setError(true);
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [admin, from, to, areaId, revision]);

    const area = data?.areas.find((item) => String(item.id) === areaId);
    const pairs = availablePairs(area, selected, data);
    const dayStarts = new Set(
        availablePairs(area, dateKey(cursor), data).map(([first]) => first),
    );
    const starts = [...new Set(pairs.map(([first]) => first))];
    const ends = pairs
        .filter(([first]) => first === start)
        .map(([, last]) => last);
    const eventsFor = (date: string) =>
        data?.reservations.filter((item) => overlapsDay(item, date)) ?? [];
    const blocksFor = (date: string) =>
        data?.blocks.filter((item) => overlapsDay(item, date)) ?? [];
    const events = eventsFor(selected);
    const blocks = blocksFor(selected);
    const displayAreas = (data?.areas ?? []).filter(
        (item) =>
            (!areaId || String(item.id) === areaId) &&
            (admin || String(item.id) === areaId),
    );

    function openDay(date: string) {
        setSelected(date);
        setStart('');
        setEnd('');
        form.clearErrors();
        setModalOpen(true);
    }
    function move(direction: number) {
        const next =
            view === 'month'
                ? new Date(
                      cursor.getFullYear(),
                      cursor.getMonth() + direction,
                      1,
                  )
                : addDays(cursor, direction * (view === 'week' ? 7 : 1));
        setCursor(next);
        setSelected(dateKey(next));
        setStart('');
        setEnd('');
    }
    async function reserve(event: FormEvent) {
        event.preventDefault();

        if (
            !area ||
            !pairs.some(([first, last]) => first === start && last === end)
        ) {
            return;
        }

        form.transform(() => ({
            common_area_id: area.id,
            starts_at: selected + ' ' + start,
            ends_at: selected + ' ' + end,
        }));

        try {
            await form.post(store.url());
            toast.success('Reserva enviada com sucesso.');
            setStart('');
            setEnd('');
            setRevision((number) => number + 1);
        } catch {
            toast.error(
                'Não foi possível confirmar a reserva. Confira a disponibilidade.',
            );
            setRevision((number) => number + 1);
        }
    }
    const periodLabel =
        view === 'day'
            ? formatDate(dateKey(cursor))
            : view === 'week'
              ? dateKey(days[0]).split('-').reverse().join('/') +
                ' – ' +
                dateKey(days[6]).split('-').reverse().join('/')
              : cursor.toLocaleDateString('pt-BR', {
                    month: 'long',
                    year: 'numeric',
                });

    return (
        <>
            <Head title="Calendário de reservas" />
            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Calendário de reservas
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {admin
                                ? 'Consulte as reservas e indisponibilidades das áreas comuns.'
                                : 'Escolha uma área e consulte os horários para reservar.'}
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={admin ? adminManage() : residentManage()}>
                            {admin ? 'Gerenciar reservas' : 'Minhas reservas'}
                        </Link>
                    </Button>
                </header>
                <label className="grid max-w-sm gap-2 text-sm font-medium">
                    Área comum
                    <select
                        className="h-10 rounded-md border bg-background px-3"
                        value={areaId}
                        onChange={(event) => {
                            setAreaId(event.target.value);
                            setStart('');
                            setEnd('');
                        }}
                    >
                        <option value="">
                            {admin ? 'Todas as áreas' : 'Selecione uma área'}
                        </option>
                        {data?.areas.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                                {item.status === 'maintenance'
                                    ? ' (em manutenção)'
                                    : ''}
                            </option>
                        ))}
                    </select>
                </label>
                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        variant="outline"
                        onClick={() => {
                            const today = new Date();
                            setCursor(today);
                            setSelected(dateKey(today));
                        }}
                    >
                        Hoje
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Período anterior"
                        onClick={() => move(-1)}
                    >
                        <ChevronLeft />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Próximo período"
                        onClick={() => move(1)}
                    >
                        <ChevronRight />
                    </Button>
                    <h2 className="text-lg font-semibold capitalize">
                        {periodLabel}
                    </h2>
                    <div className="ml-auto flex gap-1">
                        {(
                            [
                                ['day', 'Dia'],
                                ['week', 'Semana'],
                                ['month', 'Mês'],
                            ] as const
                        ).map(([value, label]) => (
                            <Button
                                key={value}
                                size="sm"
                                variant={view === value ? 'default' : 'outline'}
                                onClick={() => setView(value)}
                            >
                                {label}
                            </Button>
                        ))}
                    </div>
                </div>
                {loading && <p role="status">Carregando calendário…</p>}
                {error && (
                    <p role="alert">
                        Não foi possível carregar o calendário.{' '}
                        <button
                            className="underline"
                            onClick={() => setRevision((number) => number + 1)}
                        >
                            Tentar novamente
                        </button>
                    </p>
                )}
                {!admin && !areaId && (
                    <p className="text-sm text-muted-foreground">
                        Selecione uma área para consultar a disponibilidade.
                    </p>
                )}
                {view === 'day' ? (
                    <section
                        aria-label="Agenda do dia"
                        className="overflow-hidden rounded-xl border bg-card"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b bg-muted/30 px-4 py-3">
                            <h3 className="font-medium capitalize">
                                {formatDate(dateKey(cursor))}
                            </h3>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={loading || error}
                                onClick={() => openDay(dateKey(cursor))}
                            >
                                Ver reservas e horários
                            </Button>
                        </div>
                        {(admin || area) && !loading && !error && (
                            <div className="flex flex-wrap items-center gap-2 border-b px-4 py-3 text-sm">
                                {eventsFor(dateKey(cursor)).length === 0 ? (
                                    <span className="text-muted-foreground">
                                        Nenhuma reserva neste dia.
                                    </span>
                                ) : (
                                    eventsFor(dateKey(cursor)).map(
                                        (item, position) => (
                                            <span
                                                key={position}
                                                className="rounded bg-blue-100 px-2 py-1 text-blue-900 dark:bg-blue-950 dark:text-blue-100"
                                            >
                                                {item.start.slice(11, 16)}–
                                                {item.end.slice(11, 16)} ·{' '}
                                                {admin
                                                    ? item.area +
                                                      ' · ' +
                                                      item.status_label
                                                    : item.id
                                                      ? 'Sua reserva'
                                                      : 'Ocupado'}
                                            </span>
                                        ),
                                    )
                                )}
                            </div>
                        )}
                        <div className="max-h-[650px] overflow-y-auto">
                            {Array.from({ length: 48 }, (_, index) => {
                                const begin = clock(index * 30);
                                const endTime =
                                    index === 47
                                        ? '24:00'
                                        : clock((index + 1) * 30);
                                const day = dateKey(cursor);
                                const entries = eventsFor(day).filter(
                                    (item) =>
                                        item.start <
                                            day + ' ' + endTime + ':00' &&
                                        item.end > day + ' ' + begin + ':00',
                                );
                                const interruptions = blocksFor(day).filter(
                                    (item) =>
                                        item.start <
                                            day + ' ' + endTime + ':00' &&
                                        item.end > day + ' ' + begin + ':00',
                                );
                                const available = dayStarts.has(begin);

                                return (
                                    <button
                                        type="button"
                                        key={begin}
                                        disabled={loading || error}
                                        onClick={() => openDay(day)}
                                        className="grid min-h-12 w-full grid-cols-[4.5rem_1fr] border-b text-left text-sm last:border-0 hover:bg-muted/40"
                                    >
                                        <span className="border-r px-3 py-3 text-muted-foreground">
                                            {begin}
                                        </span>
                                        <span className="flex flex-wrap items-center gap-2 px-3 py-2">
                                            {entries.map((item, position) => (
                                                <span
                                                    key={position}
                                                    className="rounded bg-blue-100 px-2 py-1 text-blue-900 dark:bg-blue-950 dark:text-blue-100"
                                                >
                                                    {item.start.slice(11, 16)} ·{' '}
                                                    {admin
                                                        ? item.area +
                                                          ' · ' +
                                                          item.status_label
                                                        : item.id
                                                          ? item.status_label
                                                          : 'Ocupado'}
                                                </span>
                                            ))}
                                            {interruptions.map(
                                                (item, position) => (
                                                    <span
                                                        key={position}
                                                        className="rounded bg-amber-100 px-2 py-1 text-amber-900 dark:bg-amber-950 dark:text-amber-100"
                                                    >
                                                        {item.maintenance
                                                            ? 'Manutenção'
                                                            : 'Bloqueio'}
                                                        {admin
                                                            ? ' · ' +
                                                              (data?.areas.find(
                                                                  (a) =>
                                                                      a.id ===
                                                                      item.area_id,
                                                              )?.name ?? '')
                                                            : ''}
                                                    </span>
                                                ),
                                            )}
                                            {area?.status === 'maintenance' && (
                                                <span className="text-amber-700">
                                                    Em manutenção
                                                </span>
                                            )}
                                            {!admin &&
                                                area &&
                                                !entries.length &&
                                                !interruptions.length &&
                                                area.status === 'active' && (
                                                    <span
                                                        className={
                                                            available
                                                                ? 'text-emerald-700'
                                                                : 'text-muted-foreground'
                                                        }
                                                    >
                                                        {available
                                                            ? 'Disponível'
                                                            : 'Indisponível'}
                                                    </span>
                                                )}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </section>
                ) : (
                    <section
                        aria-label="Calendário"
                        className="overflow-x-auto rounded-xl border bg-card"
                    >
                        <div className="grid min-w-[700px] grid-cols-7">
                            {[
                                'Dom',
                                'Seg',
                                'Ter',
                                'Qua',
                                'Qui',
                                'Sex',
                                'Sáb',
                            ].map((name) => (
                                <div
                                    key={name}
                                    className="border-b px-2 py-3 text-center text-xs font-medium uppercase"
                                >
                                    {name}
                                </div>
                            ))}
                            {days.map((date) => {
                                const day = dateKey(date);
                                const dayEvents = eventsFor(day);
                                const dayBlocks = blocksFor(day);

                                return (
                                    <button
                                        type="button"
                                        key={day}
                                        disabled={loading || error}
                                        onClick={() => openDay(day)}
                                        className={
                                            'min-h-36 border-r border-b p-2 text-left align-top hover:bg-muted/40 ' +
                                            (date.getMonth() !==
                                                cursor.getMonth() &&
                                            view === 'month'
                                                ? 'text-muted-foreground'
                                                : '')
                                        }
                                    >
                                        <span
                                            className={
                                                'mb-2 inline-flex size-7 items-center justify-center rounded-full text-sm font-medium ' +
                                                (day === dateKey(new Date())
                                                    ? 'bg-primary text-primary-foreground'
                                                    : '')
                                            }
                                        >
                                            {date.getDate()}
                                        </span>
                                        <span className="grid gap-1">
                                            {dayEvents
                                                .slice(0, 3)
                                                .map((item, position) => (
                                                    <span
                                                        key={position}
                                                        className="truncate rounded bg-blue-600 px-2 py-1 text-xs text-white"
                                                    >
                                                        {item.start.slice(
                                                            11,
                                                            16,
                                                        )}{' '}
                                                        ·{' '}
                                                        {admin
                                                            ? item.area
                                                            : item.id
                                                              ? 'Sua reserva'
                                                              : 'Ocupado'}
                                                    </span>
                                                ))}
                                            {dayBlocks
                                                .slice(
                                                    0,
                                                    Math.max(
                                                        0,
                                                        3 - dayEvents.length,
                                                    ),
                                                )
                                                .map((item, position) => (
                                                    <span
                                                        key={position}
                                                        className="truncate rounded bg-amber-600 px-2 py-1 text-xs text-white"
                                                    >
                                                        {item.maintenance
                                                            ? 'Manutenção'
                                                            : 'Bloqueio'}
                                                        {admin
                                                            ? ' · ' +
                                                              (data?.areas.find(
                                                                  (a) =>
                                                                      a.id ===
                                                                      item.area_id,
                                                              )?.name ?? '')
                                                            : ''}
                                                    </span>
                                                ))}
                                            {area?.status === 'maintenance' &&
                                                !dayBlocks.some(
                                                    (item) => item.maintenance,
                                                ) && (
                                                    <span className="rounded bg-amber-600 px-2 py-1 text-xs text-white">
                                                        Manutenção
                                                    </span>
                                                )}
                                            {dayEvents.length +
                                                dayBlocks.length >
                                                3 && (
                                                <span className="text-xs text-muted-foreground">
                                                    +
                                                    {dayEvents.length +
                                                        dayBlocks.length -
                                                        3}{' '}
                                                    mais
                                                </span>
                                            )}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </section>
                )}
            </div>
            <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle className="capitalize">
                            {formatDate(selected)}
                        </DialogTitle>
                        <DialogDescription>
                            Reservas, bloqueios e horários da data selecionada.
                        </DialogDescription>
                    </DialogHeader>
                    {!admin && !area ? (
                        <p className="text-sm">
                            Selecione uma área para consultar a disponibilidade.
                        </p>
                    ) : (
                        <>
                            {displayAreas.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    Nenhuma reserva ou bloqueio nesta data.
                                </p>
                            )}
                            {displayAreas.map((item) => {
                                const areaEvents = events.filter(
                                    (event) => event.area_id === item.id,
                                );
                                const areaBlocks = blocks.filter(
                                    (block) => block.area_id === item.id,
                                );
                                const free = availablePairs(
                                    item,
                                    selected,
                                    data,
                                );
                                const freeStarts = [
                                    ...new Set(free.map(([first]) => first)),
                                ];

                                return (
                                    <section
                                        key={item.id}
                                        className="grid gap-2 rounded-lg border p-4 text-sm"
                                    >
                                        <h3 className="font-semibold">
                                            {item.name}
                                        </h3>
                                        {item.status === 'maintenance' && (
                                            <p className="text-amber-700">
                                                Área em manutenção
                                            </p>
                                        )}
                                        {!areaEvents.length &&
                                            !areaBlocks.length && (
                                                <p className="text-muted-foreground">
                                                    Nenhuma reserva ou bloqueio
                                                    nesta data.
                                                </p>
                                            )}
                                        {areaEvents.map((event, position) => (
                                            <div
                                                key={position}
                                                className="flex flex-wrap items-center gap-2 rounded bg-muted/50 p-2"
                                            >
                                                <span>
                                                    {event.start.slice(11, 16)}–
                                                    {event.end.slice(11, 16)} ·{' '}
                                                    {event.status_label}
                                                    {admin &&
                                                        ' · ' +
                                                            event.resident +
                                                            ' · Unidade ' +
                                                            event.unit}
                                                </span>
                                                {event.id && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={
                                                                admin
                                                                    ? adminShow(
                                                                          event.id,
                                                                      )
                                                                    : residentShow(
                                                                          event.id,
                                                                      )
                                                            }
                                                        >
                                                            Detalhes
                                                        </Link>
                                                    </Button>
                                                )}
                                            </div>
                                        ))}
                                        {areaBlocks.map((block, position) => (
                                            <p
                                                key={position}
                                                className="rounded bg-amber-100 p-2 text-amber-900 dark:bg-amber-950 dark:text-amber-100"
                                            >
                                                {block.maintenance
                                                    ? 'Manutenção'
                                                    : 'Bloqueio'}{' '}
                                                · {block.start.slice(11, 16)}–
                                                {block.end.slice(11, 16)}
                                            </p>
                                        ))}
                                        <p>
                                            <strong>
                                                Inícios disponíveis:
                                            </strong>{' '}
                                            {freeStarts.length
                                                ? freeStarts.join(', ')
                                                : 'Nenhum horário disponível'}
                                        </p>
                                    </section>
                                );
                            })}
                            {!admin && area && (
                                <section className="grid gap-3 border-t pt-4">
                                    <h3 className="font-semibold">
                                        Solicitar reserva
                                    </h3>
                                    <p className="text-sm whitespace-pre-wrap">
                                        {area.rules || 'Sem regras adicionais.'}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {area.requires_approval
                                            ? 'Sujeita à aprovação da administração.'
                                            : 'Aprovação automática.'}
                                        {area.max_reservation_minutes
                                            ? ' Duração máxima: ' +
                                              area.max_reservation_minutes +
                                              ' minutos.'
                                            : ''}
                                    </p>
                                    <form
                                        onSubmit={reserve}
                                        className="flex flex-wrap items-end gap-3"
                                    >
                                        <label className="grid gap-1 text-sm">
                                            Início
                                            <select
                                                required
                                                className="h-10 rounded-md border bg-background px-3"
                                                value={start}
                                                onChange={(event) => {
                                                    setStart(
                                                        event.target.value,
                                                    );
                                                    setEnd('');
                                                }}
                                            >
                                                <option value="">
                                                    Selecione
                                                </option>
                                                {starts.map((value) => (
                                                    <option key={value}>
                                                        {value}
                                                    </option>
                                                ))}
                                            </select>
                                        </label>
                                        <label className="grid gap-1 text-sm">
                                            Fim
                                            <select
                                                required
                                                className="h-10 rounded-md border bg-background px-3"
                                                value={end}
                                                onChange={(event) =>
                                                    setEnd(event.target.value)
                                                }
                                            >
                                                <option value="">
                                                    Selecione
                                                </option>
                                                {ends.map((value) => (
                                                    <option key={value}>
                                                        {value}
                                                    </option>
                                                ))}
                                            </select>
                                        </label>
                                        <Button
                                            disabled={
                                                !start ||
                                                !end ||
                                                form.processing ||
                                                loading ||
                                                error
                                            }
                                        >
                                            Reservar
                                        </Button>
                                    </form>
                                    {form.hasErrors && (
                                        <p
                                            role="alert"
                                            className="text-sm text-destructive"
                                        >
                                            {Object.values(form.errors).join(
                                                ' ',
                                            )}
                                        </p>
                                    )}
                                </section>
                            )}
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
