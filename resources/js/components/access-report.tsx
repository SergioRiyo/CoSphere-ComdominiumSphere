import { router, usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { IncidentSelect } from '@/components/incident-fields';
import InputError from '@/components/input-error';
import ManagementPagination from '@/components/management-pagination';
import ReportFilterSheet from '@/components/report-filter-sheet';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/admin/reports';
import { spreadsheet } from '@/routes/admin/reports/accesses';
import type {
    AccessReportItem,
    ReportFilters,
    ReportOptions,
    ReportPaginator,
} from '@/types/report';

export type AccessReportProps = {
    accesses: ReportPaginator<AccessReportItem>;
    filters: ReportFilters;
    options: ReportOptions;
};

const emptyFilters: Required<ReportFilters> = {
    access_date_from: '',
    access_date_to: '',
    access_situation: '',
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

export default function AccessReport({
    accesses,
    filters,
    options,
}: AccessReportProps) {
    const { errors } = usePage().props;
    const [draft, setDraft] = useState<Required<ReportFilters>>({
        ...emptyFilters,
        ...filters,
    });
    const [busy, setBusy] = useState(false);
    const [filtersOpen, setFiltersOpen] = useState(false);

    const visit = (
        next: Record<string, string | number | undefined>,
        closeOnSuccess = false,
    ) => {
        const query = Object.fromEntries(
            Object.entries(next).filter(
                ([, value]) => value !== '' && value !== undefined,
            ),
        );
        router.get(index.url(), query, {
            preserveScroll: true,
            replace: true,
            onSuccess: () => {
                if (closeOnSuccess) setFiltersOpen(false);
            },
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
        visit(
            {
                ...filters,
                ...Object.fromEntries(keys.map((key) => [key, draft[key]])),
                [page]: undefined,
            },
            true,
        );
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
        visit(nextFilters, true);
    };
    const paginate = (pageName: string, page: number) =>
        visit({ ...filters, [pageName]: page });

    return (
        <>
            <section
                aria-label="Indicadores"
                aria-busy={busy}
                className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
            >
                <div className="rounded-xl border p-4">
                    <p className="text-sm text-muted-foreground">
                        Total de acessos
                    </p>
                    <p className="mt-2 text-xl font-semibold tabular-nums">
                        {accesses.total}
                    </p>
                </div>
            </section>
            <section className="grid min-w-0 gap-4" aria-busy={busy}>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="font-semibold">
                        Acessos — {accesses.total} registro(s)
                    </h2>
                    <div className="flex flex-wrap items-center gap-2">
                        <ReportFilterSheet
                            title="acessos"
                            activeCount={
                                Object.values(filters).filter(Boolean).length
                            }
                            open={filtersOpen}
                            onOpenChange={(open) => {
                                if (open)
                                    setDraft({ ...emptyFilters, ...filters });
                                setFiltersOpen(open);
                            }}
                        >
                            <form
                                className="grid gap-4 px-4 pb-6"
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
                                <fieldset
                                    disabled={busy}
                                    className="grid min-w-0 gap-4"
                                >
                                    <DateField
                                        id="access-from"
                                        label="Data inicial"
                                        value={draft.access_date_from}
                                        error={errors.access_date_from}
                                        onChange={(value) =>
                                            setDraft({
                                                ...draft,
                                                access_date_from: value,
                                            })
                                        }
                                    />
                                    <DateField
                                        id="access-to"
                                        label="Data final"
                                        value={draft.access_date_to}
                                        error={errors.access_date_to}
                                        onChange={(value) =>
                                            setDraft({
                                                ...draft,
                                                access_date_to: value,
                                            })
                                        }
                                    />
                                    <IncidentSelect
                                        id="access-situation"
                                        label="Situação"
                                        value={draft.access_situation}
                                        options={options.access_situations}
                                        all
                                        onChange={(value) =>
                                            setDraft({
                                                ...draft,
                                                access_situation: value,
                                            })
                                        }
                                        error={errors.access_situation}
                                    />
                                </fieldset>
                                <p className="text-sm text-muted-foreground">
                                    Entradas registradas e tentativas
                                    persistidas pela portaria, em modo somente
                                    leitura.
                                </p>
                                <div className="flex flex-wrap gap-3">
                                    <Button disabled={busy}>
                                        Aplicar filtros
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={busy}
                                        onClick={() =>
                                            clear(
                                                [
                                                    'access_date_from',
                                                    'access_date_to',
                                                    'access_situation',
                                                ],
                                                'accesses_page',
                                            )
                                        }
                                    >
                                        Limpar filtros
                                    </Button>
                                </div>
                            </form>
                        </ReportFilterSheet>
                        <Button asChild>
                            <a href={spreadsheet.url({ query: filters })}>
                                <Download className="size-4" /> Exportar Excel
                            </a>
                        </Button>
                    </div>
                </div>
                <p className="text-sm text-muted-foreground">
                    A exportação contém todos os registros dos filtros aplicados
                    à listagem. Altere os campos e aplique os filtros antes de
                    exportar.
                </p>
                {busy && (
                    <p role="status" className="text-sm text-muted-foreground">
                        Atualizando resultados…
                    </p>
                )}
                {accesses.data.length === 0 ? (
                    <Empty />
                ) : (
                    <AccessTable items={accesses.data} />
                )}
                <ManagementPagination
                    page={accesses.current_page}
                    last={accesses.last_page}
                    busy={busy}
                    onChange={(page) => paginate('accesses_page', page)}
                />
            </section>
        </>
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

function ResponsiveTable({
    headers,
    rows,
}: {
    headers: string[];
    rows: ReactNode[][];
}) {
    return (
        <div>
            <div className="hidden overflow-x-auto rounded-xl border md:block">
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
