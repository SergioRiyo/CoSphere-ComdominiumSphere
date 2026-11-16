import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import ReportFilterSheet from '@/components/report-filter-sheet';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    index as adminIndex,
    store,
    show as adminShow,
    update,
} from '@/routes/admin/financial';
import {
    index as residentIndex,
    show as residentShow,
    boleto,
} from '@/routes/morador/financial';

export type Charge = {
    id: number;
    unit_id: number;
    unit: string;
    description: string;
    amount_cents: number;
    due_date: string;
    status: 'open' | 'overdue' | 'paid' | 'cancelled';
    paid_at: string | null;
};
type Props = {
    charges: { data: Charge[]; current_page: number; last_page: number };
    filters: Record<string, string>;
    summary: {
        open_cents: number;
        overdue_cents: number;
        overdue_count: number;
    };
    units: { id: number; label: string }[];
};
export const statusLabels: Record<Charge['status'], string> = {
    open: 'Em aberto',
    overdue: 'Vencida',
    paid: 'Paga',
    cancelled: 'Cancelada',
};
export const currency = (cents: number) =>
    (cents / 100).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
export const dateText = (date: string) => date.split('-').reverse().join('/');

export default function FinancialIndex({
    charges,
    filters,
    summary,
    units,
}: Props) {
    const { auth } = usePage().props;
    const admin = auth.user.role === 'admin';
    const index = admin ? adminIndex : residentIndex;
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Charge | null>(null);
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [clearingFilters, setClearingFilters] = useState(false);
    const form = useForm({
        unit_id: '',
        description: '',
        amount: '',
        due_date: '',
    });
    const filterForm = useForm({
        status: filters.status ?? '',
        unit_id: filters.unit_id ?? '',
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
    });

    function edit(charge: Charge | null) {
        setEditing(charge);
        form.clearErrors();
        form.setData({
            unit_id: charge ? String(charge.unit_id) : '',
            description: charge?.description ?? '',
            amount: charge ? (charge.amount_cents / 100).toFixed(2) : '',
            due_date: charge?.due_date ?? '',
        });
        setDialogOpen(true);
    }
    function submit(event: FormEvent) {
        event.preventDefault();

        if (editing) {
            form.put(update.url(editing.id), {
                preserveScroll: true,
                onSuccess: () => setDialogOpen(false),
            });
        } else {
            form.post(store.url(), {
                preserveScroll: true,
                onSuccess: () => setDialogOpen(false),
            });
        }
    }

    return (
        <>
            <Head title="Financeiro" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Financeiro</h1>
                        <p className="text-sm text-muted-foreground">
                            Simulação para demonstração. Não há integração
                            bancária nem pagamento real.
                        </p>
                    </div>
                    {admin && (
                        <Button onClick={() => edit(null)}>
                            Nova cobrança
                        </Button>
                    )}
                </header>
                <section
                    className="grid gap-3 sm:grid-cols-3"
                    aria-label="Resumo financeiro"
                >
                    <div className="rounded-xl border p-4">
                        <p className="text-sm text-muted-foreground">
                            Total em aberto (inclui vencidas)
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {currency(summary.open_cents)}
                        </p>
                    </div>
                    <div className="rounded-xl border p-4">
                        <p className="text-sm text-muted-foreground">
                            Total vencido
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {currency(summary.overdue_cents)}
                        </p>
                    </div>
                    <div className="rounded-xl border p-4">
                        <p className="text-sm text-muted-foreground">
                            Cobranças vencidas
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {summary.overdue_count}
                        </p>
                    </div>
                </section>

                {admin && (
                    <p className="text-sm text-muted-foreground">
                        Para consultar a inadimplência, selecione “Vencidas”. Os
                        totais respeitam os filtros de unidade e vencimento.
                    </p>
                )}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="font-semibold">Cobranças</h2>
                    <ReportFilterSheet
                        title="financeiro"
                        activeCount={
                            [
                                'status',
                                ...(admin ? ['unit_id'] : []),
                                'date_from',
                                'date_to',
                            ].filter((key) => Boolean(filters[key])).length
                        }
                        open={filtersOpen}
                        onOpenChange={(open) => {
                            if (open) {
                                filterForm.setData({
                                    status: filters.status ?? '',
                                    unit_id: filters.unit_id ?? '',
                                    date_from: filters.date_from ?? '',
                                    date_to: filters.date_to ?? '',
                                });
                                filterForm.clearErrors();
                            }

                            setFiltersOpen(open);
                        }}
                    >
                        <form
                            className="grid gap-4 px-4 pb-6"
                            onSubmit={(event) => {
                                event.preventDefault();
                                filterForm.get(index.url(), {
                                    preserveState: true,
                                    preserveScroll: true,
                                    onSuccess: () => setFiltersOpen(false),
                                });
                            }}
                        >
                            <label className="grid gap-2 text-sm">
                                Situação
                                <select
                                    className="h-10 rounded-md border bg-background px-3"
                                    value={filterForm.data.status}
                                    onChange={(event) =>
                                        filterForm.setData(
                                            'status',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">Todas</option>
                                    <option value="open">Em aberto</option>
                                    <option value="overdue">Vencidas</option>
                                    <option value="paid">Pagas</option>
                                    <option value="cancelled">
                                        Canceladas
                                    </option>
                                </select>
                            </label>
                            {admin && (
                                <label className="grid gap-2 text-sm">
                                    Unidade
                                    <select
                                        className="h-10 rounded-md border bg-background px-3"
                                        value={filterForm.data.unit_id}
                                        onChange={(event) =>
                                            filterForm.setData(
                                                'unit_id',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="">Todas</option>
                                        {units.map((unit) => (
                                            <option
                                                key={unit.id}
                                                value={unit.id}
                                            >
                                                {unit.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            )}
                            <label className="grid gap-2 text-sm">
                                Vencimento inicial
                                <input
                                    type="date"
                                    className="h-10 rounded-md border bg-background px-3"
                                    value={filterForm.data.date_from}
                                    onChange={(event) =>
                                        filterForm.setData(
                                            'date_from',
                                            event.target.value,
                                        )
                                    }
                                />
                            </label>
                            <label className="grid gap-2 text-sm">
                                Vencimento final
                                <input
                                    type="date"
                                    className="h-10 rounded-md border bg-background px-3"
                                    value={filterForm.data.date_to}
                                    onChange={(event) =>
                                        filterForm.setData(
                                            'date_to',
                                            event.target.value,
                                        )
                                    }
                                />
                            </label>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    disabled={
                                        filterForm.processing || clearingFilters
                                    }
                                >
                                    Aplicar filtros
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={
                                        filterForm.processing || clearingFilters
                                    }
                                    onClick={() => {
                                        router.get(
                                            index.url(),
                                            {},
                                            {
                                                preserveScroll: true,
                                                onStart: () =>
                                                    setClearingFilters(true),
                                                onSuccess: () => {
                                                    filterForm.setData({
                                                        status: '',
                                                        unit_id: '',
                                                        date_from: '',
                                                        date_to: '',
                                                    });
                                                    filterForm.clearErrors();
                                                    setFiltersOpen(false);
                                                },
                                                onFinish: () =>
                                                    setClearingFilters(false),
                                            },
                                        );
                                    }}
                                >
                                    Limpar filtros
                                </Button>
                            </div>
                            {(filterForm.errors.status ||
                                filterForm.errors.unit_id ||
                                filterForm.errors.date_from ||
                                filterForm.errors.date_to) && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {filterForm.errors.status ||
                                        filterForm.errors.unit_id ||
                                        filterForm.errors.date_from ||
                                        filterForm.errors.date_to}
                                </p>
                            )}
                        </form>
                    </ReportFilterSheet>
                </div>
                <section className="grid gap-3" aria-label="Cobranças">
                    {charges.data.length === 0 && (
                        <p className="rounded-xl border p-6 text-center text-muted-foreground">
                            Nenhuma cobrança encontrada.
                        </p>
                    )}
                    {charges.data.map((charge) => (
                        <article
                            key={charge.id}
                            className="flex flex-wrap items-center justify-between gap-4 rounded-xl border p-4"
                        >
                            <div className="grid gap-1">
                                <h2 className="font-semibold">
                                    {charge.description}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {charge.unit} · Vencimento{' '}
                                    {dateText(charge.due_date)} ·{' '}
                                    {statusLabels[charge.status]}
                                </p>
                                <p className="font-medium">
                                    {currency(charge.amount_cents)}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button variant="outline" asChild>
                                    <Link
                                        href={
                                            admin
                                                ? adminShow(charge.id)
                                                : residentShow(charge.id)
                                        }
                                    >
                                        Detalhes
                                    </Link>
                                </Button>
                                {admin &&
                                    ['open', 'overdue'].includes(
                                        charge.status,
                                    ) && (
                                        <Button
                                            variant="outline"
                                            onClick={() => edit(charge)}
                                        >
                                            Editar
                                        </Button>
                                    )}
                                {!admin &&
                                    ['open', 'overdue'].includes(
                                        charge.status,
                                    ) && (
                                        <Button asChild>
                                            <a href={boleto.url(charge.id)}>
                                                Baixar boleto simulado
                                            </a>
                                        </Button>
                                    )}
                            </div>
                        </article>
                    ))}
                </section>
                <nav className="flex items-center gap-3" aria-label="Paginação">
                    {charges.current_page > 1 && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.get(index.url(), {
                                    ...filters,
                                    page: charges.current_page - 1,
                                })
                            }
                        >
                            Anterior
                        </Button>
                    )}
                    <span className="text-sm">
                        Página {charges.current_page} de {charges.last_page}
                    </span>
                    {charges.current_page < charges.last_page && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.get(index.url(), {
                                    ...filters,
                                    page: charges.current_page + 1,
                                })
                            }
                        >
                            Próxima
                        </Button>
                    )}
                </nav>
            </div>
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {editing
                                ? 'Editar cobrança simulada'
                                : 'Nova cobrança simulada'}
                        </DialogTitle>
                        <DialogDescription>
                            Os dados são apenas para demonstração. Nenhum
                            pagamento será realizado.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="grid gap-4">
                        <label className="grid gap-1 text-sm">
                            Unidade
                            <select
                                required
                                className="h-10 rounded-md border bg-background px-3"
                                value={form.data.unit_id}
                                onChange={(event) =>
                                    form.setData('unit_id', event.target.value)
                                }
                            >
                                <option value="">Selecione</option>
                                {units.map((unit) => (
                                    <option key={unit.id} value={unit.id}>
                                        {unit.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="grid gap-1 text-sm">
                            Descrição
                            <input
                                required
                                maxLength={200}
                                className="h-10 rounded-md border bg-background px-3"
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                            />
                        </label>
                        <label className="grid gap-1 text-sm">
                            Valor em reais
                            <input
                                required
                                type="number"
                                min="0.01"
                                max="9999999.99"
                                step="0.01"
                                className="h-10 rounded-md border bg-background px-3"
                                value={form.data.amount}
                                onChange={(event) =>
                                    form.setData('amount', event.target.value)
                                }
                            />
                        </label>
                        <label className="grid gap-1 text-sm">
                            Vencimento
                            <input
                                required
                                type="date"
                                className="h-10 rounded-md border bg-background px-3"
                                value={form.data.due_date}
                                onChange={(event) =>
                                    form.setData('due_date', event.target.value)
                                }
                            />
                        </label>
                        {form.hasErrors && (
                            <div
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {Object.values(form.errors).map((error, i) => (
                                    <p key={i}>{error}</p>
                                ))}
                            </div>
                        )}
                        <Button disabled={form.processing}>
                            {form.processing ? 'Salvando…' : 'Salvar cobrança'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
