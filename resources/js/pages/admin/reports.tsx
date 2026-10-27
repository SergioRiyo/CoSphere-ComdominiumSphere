import { Head, router, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    ClipboardList,
    DoorOpen,
    SlidersHorizontal,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { IncidentSelect } from '@/components/incident-fields';
import InputError from '@/components/input-error';
import ManagementPagination from '@/components/management-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/admin/reports';
import type {
    AccessReportItem,
    IncidentReportItem,
    ReportFilters,
    ReportOptions,
    ReportPaginator,
    ReservationReportItem,
} from '@/types/report';

type Props = {
    reservations: ReportPaginator<ReservationReportItem>;
    accesses: ReportPaginator<AccessReportItem>;
    incidents: ReportPaginator<IncidentReportItem>;
    filters: ReportFilters;
    options: ReportOptions;
};

const emptyFilters: Required<ReportFilters> = {
    reservation_date_from: '',
    reservation_date_to: '',
    reservation_status: '',
    common_area_id: '',
    access_date_from: '',
    access_date_to: '',
    access_situation: '',
    incident_date_from: '',
    incident_date_to: '',
    incident_category: '',
    incident_status: '',
};

function dateTime(value: string | null) {
    return value
        ? new Intl.DateTimeFormat('pt-BR', {
              dateStyle: 'short',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'Não registrado';
}

function Status({ children }: { children: ReactNode }) {
    return <Badge variant="secondary">{children}</Badge>;
}

function Metric({
    label,
    value,
    icon: Icon,
}: {
    label: string;
    value: number;
    icon: typeof CalendarDays;
}) {
    return (
        <Card className="overflow-hidden border-cosphere-line/80 shadow-sm dark:border-border">
            <CardContent className="flex items-center justify-between gap-4 p-5">
                <div>
                    <p className="text-sm text-muted-foreground">{label}</p>
                    <p className="mt-1 text-3xl font-semibold tabular-nums">
                        {value}
                    </p>
                </div>
                <span className="grid size-12 place-items-center rounded-2xl bg-cosphere-blue/10 text-cosphere-blue">
                    <Icon className="size-5" aria-hidden="true" />
                </span>
            </CardContent>
        </Card>
    );
}

function DateField({
    id,
    label,
    value,
    error,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                type="date"
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
            <InputError message={error} />
        </div>
    );
}

function Empty() {
    return (
        <p className="p-5 text-sm text-muted-foreground">
            Nenhum registro encontrado para os filtros selecionados.
        </p>
    );
}

export default function Reports({
    reservations,
    accesses,
    incidents,
    filters,
    options,
}: Props) {
    const { errors } = usePage().props;
    const [draft, setDraft] = useState<Required<ReportFilters>>({
        ...emptyFilters,
        ...filters,
    });
    const [busy, setBusy] = useState(false);

    const visit = (next: Record<string, string | number | undefined>) => {
        const query = Object.fromEntries(
            Object.entries(next).filter(
                ([, value]) => value !== '' && value !== undefined,
            ),
        );
        router.get(index.url(), query, {
            preserveScroll: true,
            replace: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
        });
    };
    const apply = (
        event: FormEvent,
        keys: (keyof ReportFilters)[],
        page: string,
    ) => {
        event.preventDefault();
        visit({
            ...filters,
            ...Object.fromEntries(keys.map((key) => [key, draft[key]])),
            [page]: undefined,
        });
    };
    const clear = (keys: (keyof ReportFilters)[], page: string) => {
        const nextDraft = { ...draft };
        const nextFilters: Record<string, string | undefined> = {
            ...filters,
            [page]: undefined,
        };
        keys.forEach((key) => {
            nextDraft[key] = '';
            nextFilters[key] = undefined;
        });
        setDraft(nextDraft);
        visit(nextFilters);
    };
    const paginate = (pageName: string, page: number) =>
        visit({ ...filters, [pageName]: page });

    return (
        <>
            <Head title="Relatórios" />
            <main className="mx-auto grid w-full max-w-7xl gap-8 p-4 sm:p-6">
                <header className="max-w-3xl">
                    <p className="text-sm font-medium text-cosphere-blue">
                        Visão administrativa
                    </p>
                    <h1 className="mt-1 text-3xl font-semibold tracking-tight">
                        Relatórios
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Consulte reservas, acessos de visitantes e ocorrências.
                        Os totais acompanham os filtros aplicados em cada seção.
                    </p>
                </header>

                <section
                    className="grid gap-4 sm:grid-cols-3"
                    aria-label="Totais dos relatórios"
                >
                    <Metric
                        label="Reservas"
                        value={reservations.total}
                        icon={CalendarDays}
                    />
                    <Metric
                        label="Acessos"
                        value={accesses.total}
                        icon={DoorOpen}
                    />
                    <Metric
                        label="Ocorrências"
                        value={incidents.total}
                        icon={ClipboardList}
                    />
                </section>

                <ReportCard
                    title="Reservas"
                    description="Período considerado pelo início da reserva."
                >
                    <form
                        className="grid gap-4 border-b p-5 md:grid-cols-2 xl:grid-cols-4"
                        onSubmit={(event) =>
                            apply(
                                event,
                                [
                                    'reservation_date_from',
                                    'reservation_date_to',
                                    'reservation_status',
                                    'common_area_id',
                                ],
                                'reservations_page',
                            )
                        }
                    >
                        <DateField
                            id="reservation-from"
                            label="Data inicial"
                            value={draft.reservation_date_from}
                            error={errors.reservation_date_from}
                            onChange={(value) =>
                                setDraft({
                                    ...draft,
                                    reservation_date_from: value,
                                })
                            }
                        />
                        <DateField
                            id="reservation-to"
                            label="Data final"
                            value={draft.reservation_date_to}
                            error={errors.reservation_date_to}
                            onChange={(value) =>
                                setDraft({
                                    ...draft,
                                    reservation_date_to: value,
                                })
                            }
                        />
                        <IncidentSelect
                            id="reservation-status"
                            label="Status"
                            value={draft.reservation_status}
                            options={options.reservation_statuses}
                            all
                            onChange={(value) =>
                                setDraft({
                                    ...draft,
                                    reservation_status: value,
                                })
                            }
                            error={errors.reservation_status}
                        />
                        <IncidentSelect
                            id="reservation-area"
                            label="Área comum"
                            value={draft.common_area_id}
                            options={options.common_areas}
                            all
                            onChange={(value) =>
                                setDraft({ ...draft, common_area_id: value })
                            }
                            error={errors.common_area_id}
                        />
                        <FilterActions
                            busy={busy}
                            onClear={() =>
                                clear(
                                    [
                                        'reservation_date_from',
                                        'reservation_date_to',
                                        'reservation_status',
                                        'common_area_id',
                                    ],
                                    'reservations_page',
                                )
                            }
                        />
                    </form>
                    {reservations.data.length === 0 ? (
                        <Empty />
                    ) : (
                        <ReservationTable items={reservations.data} />
                    )}
                    <Pagination
                        paginator={reservations}
                        busy={busy}
                        onChange={(page) => paginate('reservations_page', page)}
                    />
                </ReportCard>

                <ReportCard
                    title="Acessos"
                    description="Entradas registradas e tentativas persistidas pela portaria, em modo somente leitura."
                >
                    <form
                        className="grid gap-4 border-b p-5 md:grid-cols-3"
                        onSubmit={(event) =>
                            apply(
                                event,
                                [
                                    'access_date_from',
                                    'access_date_to',
                                    'access_situation',
                                ],
                                'accesses_page',
                            )
                        }
                    >
                        <DateField
                            id="access-from"
                            label="Data inicial"
                            value={draft.access_date_from}
                            error={errors.access_date_from}
                            onChange={(value) =>
                                setDraft({ ...draft, access_date_from: value })
                            }
                        />
                        <DateField
                            id="access-to"
                            label="Data final"
                            value={draft.access_date_to}
                            error={errors.access_date_to}
                            onChange={(value) =>
                                setDraft({ ...draft, access_date_to: value })
                            }
                        />
                        <IncidentSelect
                            id="access-situation"
                            label="Situação"
                            value={draft.access_situation}
                            options={options.access_situations}
                            all
                            onChange={(value) =>
                                setDraft({ ...draft, access_situation: value })
                            }
                            error={errors.access_situation}
                        />
                        <FilterActions
                            busy={busy}
                            onClear={() =>
                                clear(
                                    [
                                        'access_date_from',
                                        'access_date_to',
                                        'access_situation',
                                    ],
                                    'accesses_page',
                                )
                            }
                        />
                    </form>
                    {accesses.data.length === 0 ? (
                        <Empty />
                    ) : (
                        <AccessTable items={accesses.data} />
                    )}
                    <Pagination
                        paginator={accesses}
                        busy={busy}
                        onChange={(page) => paginate('accesses_page', page)}
                    />
                </ReportCard>

                <ReportCard
                    title="Ocorrências"
                    description="Período considerado pela data de abertura."
                >
                    <form
                        className="grid gap-4 border-b p-5 md:grid-cols-2 xl:grid-cols-4"
                        onSubmit={(event) =>
                            apply(
                                event,
                                [
                                    'incident_date_from',
                                    'incident_date_to',
                                    'incident_category',
                                    'incident_status',
                                ],
                                'incidents_page',
                            )
                        }
                    >
                        <DateField
                            id="incident-from"
                            label="Data inicial"
                            value={draft.incident_date_from}
                            error={errors.incident_date_from}
                            onChange={(value) =>
                                setDraft({
                                    ...draft,
                                    incident_date_from: value,
                                })
                            }
                        />
                        <DateField
                            id="incident-to"
                            label="Data final"
                            value={draft.incident_date_to}
                            error={errors.incident_date_to}
                            onChange={(value) =>
                                setDraft({ ...draft, incident_date_to: value })
                            }
                        />
                        <IncidentSelect
                            id="incident-category"
                            label="Categoria"
                            value={draft.incident_category}
                            options={options.incident_categories}
                            all
                            onChange={(value) =>
                                setDraft({ ...draft, incident_category: value })
                            }
                            error={errors.incident_category}
                        />
                        <IncidentSelect
                            id="incident-status"
                            label="Status"
                            value={draft.incident_status}
                            options={options.incident_statuses}
                            all
                            onChange={(value) =>
                                setDraft({ ...draft, incident_status: value })
                            }
                            error={errors.incident_status}
                        />
                        <FilterActions
                            busy={busy}
                            onClear={() =>
                                clear(
                                    [
                                        'incident_date_from',
                                        'incident_date_to',
                                        'incident_category',
                                        'incident_status',
                                    ],
                                    'incidents_page',
                                )
                            }
                        />
                    </form>
                    {incidents.data.length === 0 ? (
                        <Empty />
                    ) : (
                        <IncidentTable items={incidents.data} />
                    )}
                    <Pagination
                        paginator={incidents}
                        busy={busy}
                        onChange={(page) => paginate('incidents_page', page)}
                    />
                </ReportCard>
            </main>
        </>
    );
}

function ReportCard({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <Card className="overflow-hidden p-0">
            <CardHeader className="border-b bg-muted/25 p-5">
                <CardTitle className="flex items-center gap-2">
                    <SlidersHorizontal className="size-4 text-cosphere-blue" />
                    {title}
                </CardTitle>
                <p className="text-sm text-muted-foreground">{description}</p>
            </CardHeader>
            {children}
        </Card>
    );
}

function FilterActions({
    busy,
    onClear,
}: {
    busy: boolean;
    onClear: () => void;
}) {
    return (
        <div className="flex flex-wrap gap-2 md:col-span-full">
            <Button disabled={busy}>Aplicar filtros</Button>
            <Button
                type="button"
                variant="outline"
                disabled={busy}
                onClick={onClear}
            >
                Limpar seção
            </Button>
        </div>
    );
}

function Pagination<T>({
    paginator,
    busy,
    onChange,
}: {
    paginator: ReportPaginator<T>;
    busy: boolean;
    onChange: (page: number) => void;
}) {
    return (
        <div className="border-t p-5">
            <ManagementPagination
                page={paginator.current_page}
                last={paginator.last_page}
                busy={busy}
                onChange={onChange}
            />
        </div>
    );
}

function ReservationTable({ items }: { items: ReservationReportItem[] }) {
    return (
        <ResponsiveTable
            headers={['Área', 'Morador / unidade', 'Período', 'Status']}
            rows={items.map((item) => [
                item.area,
                <span key="resident">
                    {item.resident}
                    <small className="block text-muted-foreground">
                        {item.unit}
                    </small>
                </span>,
                <span key="period">
                    {dateTime(item.starts_at)}
                    <small className="block text-muted-foreground">
                        até {dateTime(item.ends_at)}
                    </small>
                </span>,
                <Status key="status">{item.status_label}</Status>,
            ])}
        />
    );
}

function AccessTable({ items }: { items: AccessReportItem[] }) {
    return (
        <ResponsiveTable
            headers={[
                'Visitante / unidade',
                'Entrada / saída',
                'Porteiros',
                'Situação',
            ]}
            rows={items.map((item) => [
                <span key="visitor">
                    {item.visitor}
                    <small className="block text-muted-foreground">
                        {item.unit}
                    </small>
                </span>,
                <span key="period">
                    {dateTime(item.entry_at)}
                    <small className="block text-muted-foreground">
                        Saída: {dateTime(item.exit_at)}
                    </small>
                </span>,
                <span key="doormen">
                    Entrada: {item.entry_doorman || 'Não identificado'}
                    <small className="block text-muted-foreground">
                        Saída: {item.exit_doorman || 'Não identificado'}
                    </small>
                </span>,
                <Status key="status">{item.situation_label}</Status>,
            ])}
        />
    );
}

function IncidentTable({ items }: { items: IncidentReportItem[] }) {
    return (
        <ResponsiveTable
            headers={[
                'Categoria / título',
                'Unidade',
                'Status',
                'Abertura / atualização',
            ]}
            rows={items.map((item) => [
                <span key="incident">
                    {item.category_label}
                    <small className="block font-medium text-foreground">
                        {item.title}
                    </small>
                </span>,
                item.unit,
                <Status key="status">{item.status_label}</Status>,
                <span key="dates">
                    {dateTime(item.opened_at)}
                    <small className="block text-muted-foreground">
                        Atualizada: {dateTime(item.updated_at)}
                    </small>
                </span>,
            ])}
        />
    );
}

function ResponsiveTable({
    headers,
    rows,
}: {
    headers: string[];
    rows: ReactNode[][];
}) {
    return (
        <div>
            <div className="hidden overflow-x-auto md:block">
                <table className="w-full text-left text-sm">
                    <thead className="border-b bg-muted/15">
                        <tr>
                            {headers.map((header) => (
                                <th
                                    key={header}
                                    className="px-5 py-3 font-medium"
                                >
                                    {header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, index) => (
                            <tr key={index} className="border-b last:border-0">
                                {row.map((cell, cellIndex) => (
                                    <td
                                        key={cellIndex}
                                        className="px-5 py-4 align-top"
                                    >
                                        {cell}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="grid gap-3 p-4 md:hidden">
                {rows.map((row, index) => (
                    <article
                        key={index}
                        className="grid gap-3 rounded-xl border p-4"
                    >
                        {row.map((cell, cellIndex) => (
                            <div key={cellIndex}>
                                <p className="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    {headers[cellIndex]}
                                </p>
                                <div className="text-sm break-words">
                                    {cell}
                                </div>
                            </div>
                        ))}
                    </article>
                ))}
            </div>
        </div>
    );
}

Reports.layout = { breadcrumbs: [{ title: 'Relatórios', href: index() }] };
