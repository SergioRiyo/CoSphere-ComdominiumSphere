import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { IncidentSelect } from '@/components/incident-fields';
import InputError from '@/components/input-error';
import ManagementPagination from '@/components/management-pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    index as overview,
    incidents,
    maintenances,
    reservations,
} from '@/routes/admin/reports';
import { exportMethod as exportIncidents } from '@/routes/admin/reports/incidents';
import { exportMethod as exportMaintenances } from '@/routes/admin/reports/maintenances';
import { exportMethod as exportReservations } from '@/routes/admin/reports/reservations';
import type { Paged } from '@/types/maintenance';

type Kind = 'reservations' | 'incidents' | 'maintenances';
type Option = { value: string; label: string };
type Props = {
    kind: Kind;
    records: Paged<Record<string, string | number | null> & { id: number }>;
    filters: Record<string, string | null>;
    options: Record<string, Option[]>;
    metrics: {
        total: number;
        statuses: (Option & { count: number })[];
        cost_total?: string;
        cost_average?: string | null;
        cost_count?: number;
    };
};
const reports = {
    reservations: {
        label: 'Reservas',
        route: reservations,
        csv: exportReservations,
    },
    incidents: { label: 'Ocorrências', route: incidents, csv: exportIncidents },
    maintenances: {
        label: 'Manutenções',
        route: maintenances,
        csv: exportMaintenances,
    },
};
const columns: Record<Kind, [string, string][]> = {
    reservations: [
        ['id', 'ID'],
        ['area', 'Área comum'],
        ['date', 'Data'],
        ['start', 'Início'],
        ['end', 'Fim'],
        ['status', 'Status'],
        ['resident', 'Morador'],
        ['unit', 'Unidade'],
    ],
    incidents: [
        ['id', 'ID'],
        ['title', 'Título'],
        ['type', 'Tipo'],
        ['category', 'Categoria'],
        ['priority', 'Prioridade'],
        ['status', 'Status'],
        ['resident', 'Morador'],
        ['unit', 'Unidade'],
        ['created_at', 'Abertura'],
    ],
    maintenances: [
        ['id', 'ID'],
        ['description', 'Descrição'],
        ['unit', 'Unidade'],
        ['origin', 'Origem'],
        ['incident', 'Ocorrência vinculada'],
        ['admin', 'Responsável'],
        ['provider', 'Prestador'],
        ['status', 'Status'],
        ['created_at', 'Abertura'],
        ['scheduled_at', 'Agendamento'],
        ['executed_at', 'Execução'],
        ['cost', 'Custo'],
    ],
};
const selects: Record<Kind, [string, string, string][]> = {
    reservations: [
        ['common_area_id', 'Área comum', 'areas'],
        ['status', 'Status', 'statuses'],
        ['unit_id', 'Unidade', 'units'],
        ['resident_id', 'Morador', 'residents'],
    ],
    incidents: [
        ['type', 'Tipo', 'types'],
        ['category', 'Categoria', 'categories'],
        ['priority', 'Prioridade', 'priorities'],
        ['status', 'Status', 'statuses'],
        ['unit_id', 'Unidade', 'units'],
        ['resident_id', 'Morador', 'residents'],
    ],
    maintenances: [
        ['date_reference', 'Referência do período', 'references'],
        ['status', 'Status', 'statuses'],
        ['service_provider_id', 'Prestador', 'providers'],
        ['origin', 'Origem', 'origins'],
    ],
};
function money(value: string | null | undefined) {
    if (value == null) {
        return 'Não informado';
    }

    const [integer, cents] = value.split('.');

    return `R$ ${integer.replace(/\B(?=(\d{3})+(?!\d))/g, '.')},${cents}`;
}
export default function Reports(props: Props) {
    return <Report key={props.kind} {...props} />;
}
function Report({ kind, records, filters, options, metrics }: Props) {
    const current = reports[kind];
    const initial: Record<string, string> = {
        date_from: filters.date_from || '',
        date_to: filters.date_to || '',
    };

    for (const [key] of selects[kind]) {
        initial[key] =
            filters[key] || (key === 'date_reference' ? 'opening' : '');
    }

    const form = useForm(initial);
    const [busy, setBusy] = useState(false);
    const [failure, setFailure] = useState('');
    const loading = busy || form.processing;
    const callbacks = {
        preserveScroll: true,
        onStart: () => {
            setBusy(true);
            setFailure('');
        },
        onFinish: () => setBusy(false),
        onError: () => {
            setFailure('Confira os filtros informados e tente novamente.');
        },
        onHttpException: () => {
            setFailure(
                'Não foi possível consultar o relatório. Tente novamente.',
            );

            return false;
        },
        onNetworkError: () => {
            setFailure('Falha de conexão. Tente novamente.');

            return false;
        },
    };

    return (
        <>
            <Head title={`Relatórios — ${current.label}`} />
            <div className="grid min-w-0 gap-6 p-4 sm:p-6">
                <header>
                    <h1 className="text-2xl font-semibold">
                        Relatórios gerenciais
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Consulte registros e exporte os resultados filtrados.
                    </p>
                </header>
                <nav aria-label="Relatórios" className="flex flex-wrap gap-2">
                    <Button asChild variant="outline">
                        <Link href={overview()}>Acessos</Link>
                    </Button>
                    {Object.entries(reports).map(([key, report]) => (
                        <Button
                            key={key}
                            asChild
                            variant={key === kind ? 'default' : 'outline'}
                        >
                            <Link
                                href={report.route()}
                                aria-current={key === kind ? 'page' : undefined}
                            >
                                {report.label}
                            </Link>
                        </Button>
                    ))}
                </nav>
                <form
                    className="grid gap-4 rounded-xl border p-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.get(current.route.url(), {
                            ...callbacks,
                            onSuccess: () =>
                                toast.success('Filtros aplicados.'),
                        });
                    }}
                >
                    <h2 className="font-semibold">
                        Filtros de {current.label.toLowerCase()}
                    </h2>
                    <fieldset
                        disabled={loading}
                        className="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-4"
                    >
                        {selects[kind].map(([key, label, source]) => (
                            <IncidentSelect
                                key={key}
                                id={key}
                                label={label}
                                value={form.data[key]}
                                options={options[source]}
                                all={key !== 'date_reference'}
                                onChange={(value) => form.setData(key, value)}
                                error={form.errors[key]}
                                disabled={loading}
                            />
                        ))}
                        {(['date_from', 'date_to'] as const).map((key) => (
                            <div key={key} className="grid gap-2">
                                <Label htmlFor={key}>
                                    {key === 'date_from'
                                        ? 'Data inicial'
                                        : 'Data final'}
                                </Label>
                                <Input
                                    id={key}
                                    type="date"
                                    value={form.data[key]}
                                    onChange={(event) =>
                                        form.setData(key, event.target.value)
                                    }
                                />
                                <InputError message={form.errors[key]} />
                            </div>
                        ))}
                    </fieldset>
                    <p className="text-sm text-muted-foreground">
                        {kind === 'reservations'
                            ? 'Período pelo início da reserva.'
                            : kind === 'incidents'
                              ? 'Período pela abertura da ocorrência.'
                              : 'Período pela referência selecionada.'}{' '}
                        As datas inicial e final estão incluídas. Horários no
                        fuso do sistema (UTC).
                    </p>
                    <div className="flex flex-wrap gap-3">
                        <Button disabled={loading}>
                            {loading ? 'Consultando…' : 'Aplicar filtros'}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={loading}
                            onClick={() =>
                                router.get(
                                    current.route.url(),
                                    {},
                                    { ...callbacks, preserveState: false },
                                )
                            }
                        >
                            Limpar filtros
                        </Button>
                    </div>
                    {failure && (
                        <p role="alert" className="text-sm text-destructive">
                            {failure}
                        </p>
                    )}
                </form>
                <section
                    aria-label="Indicadores"
                    aria-busy={loading}
                    className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                >
                    {[
                        { label: 'Total', value: metrics.total },
                        ...metrics.statuses.map((status) => ({
                            label: status.label,
                            value: status.count,
                        })),
                        ...(kind === 'maintenances'
                            ? [
                                  {
                                      label: 'Custo total',
                                      value: money(metrics.cost_total),
                                  },
                                  {
                                      label: 'Custo médio',
                                      value:
                                          metrics.cost_average == null
                                              ? 'Não disponível'
                                              : money(metrics.cost_average),
                                  },
                              ]
                            : []),
                    ].map((metric) => (
                        <div
                            key={metric.label}
                            className="rounded-xl border p-4"
                        >
                            <p className="text-sm text-muted-foreground">
                                {metric.label}
                            </p>
                            <p className="mt-2 text-xl font-semibold tabular-nums">
                                {metric.value}
                            </p>
                        </div>
                    ))}
                </section>
                {kind === 'maintenances' && (
                    <p className="text-sm text-muted-foreground">
                        Média calculada sobre {metrics.cost_count} registro(s)
                        com custo informado, incluindo valores zero e excluindo
                        custos não informados.
                    </p>
                )}
                <section className="grid min-w-0 gap-4" aria-busy={loading}>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="font-semibold">
                            {current.label} — {records.total} registro(s)
                        </h2>
                        <Button asChild variant="outline">
                            <a href={current.csv.url({ query: filters })}>
                                <Download className="size-4" />
                                Exportar CSV
                            </a>
                        </Button>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        O CSV contém todos os registros dos filtros aplicados à
                        listagem. Altere os campos e aplique os filtros antes de
                        exportar.
                    </p>
                    {loading && (
                        <p
                            role="status"
                            className="text-sm text-muted-foreground"
                        >
                            Atualizando resultados…
                        </p>
                    )}
                    {records.data.length === 0 ? (
                        <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                            Nenhum registro encontrado para os filtros
                            selecionados.
                        </p>
                    ) : (
                        <div
                            className="max-w-full overflow-x-auto rounded-xl border"
                            tabIndex={0}
                            role="region"
                            aria-label={`Tabela de ${current.label.toLowerCase()}`}
                        >
                            <table className="w-full text-left text-sm">
                                <caption className="sr-only">
                                    {current.label} filtradas, página{' '}
                                    {records.current_page}
                                </caption>
                                <thead className="bg-muted/50">
                                    <tr>
                                        {columns[kind].map(([key, label]) => (
                                            <th
                                                key={key}
                                                scope="col"
                                                className="px-4 py-3 font-medium whitespace-nowrap"
                                            >
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {records.data.map((row) => (
                                        <tr key={row.id} className="border-t">
                                            {columns[kind].map(([key]) => (
                                                <td
                                                    key={key}
                                                    className="max-w-80 min-w-28 px-4 py-3 align-top wrap-break-word"
                                                >
                                                    {key === 'cost'
                                                        ? money(
                                                              row.cost as
                                                                  | string
                                                                  | null,
                                                          )
                                                        : (row[key] ?? '—')}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <ManagementPagination
                        page={records.current_page}
                        last={records.last_page}
                        busy={loading}
                        onChange={(page) =>
                            router.get(
                                current.route.url(),
                                { ...filters, page },
                                callbacks,
                            )
                        }
                    />
                </section>
            </div>
        </>
    );
}
