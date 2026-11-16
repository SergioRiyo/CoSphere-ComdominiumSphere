import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { currency, dateText, statusLabels } from '@/pages/financial/index';
import type { Charge } from '@/pages/financial/index';
import { change, index as adminIndex } from '@/routes/admin/financial';
import { boleto, index as residentIndex } from '@/routes/morador/financial';

type Snapshot = {
    unit_id: number;
    description: string;
    amount_cents: number;
    due_date: string;
    status: string;
    paid_at: string | null;
};
type History = {
    action: string;
    actor: string;
    reason: string | null;
    before: Snapshot | null;
    after: Snapshot;
    created_at: string;
};
type Details = Charge & { history?: History[] };
const actions: Record<string, string> = {
    created: 'Criou a cobrança',
    updated: 'Editou a cobrança',
    pay: 'Registrou pagamento simulado',
    reopen: 'Reabriu a cobrança',
    cancel: 'Cancelou a cobrança',
};
const today = () => {
    const value = new Date();

    return [
        value.getFullYear(),
        String(value.getMonth() + 1).padStart(2, '0'),
        String(value.getDate()).padStart(2, '0'),
    ].join('-');
};

export default function FinancialShow({ charge }: { charge: Details }) {
    const admin = usePage().props.auth.user.role === 'admin';
    const [action, setAction] = useState<'pay' | 'reopen' | 'cancel' | null>(
        null,
    );
    const form = useForm({ action: '', paid_at: today(), reason: '' });

    function choose(next: 'pay' | 'reopen' | 'cancel') {
        form.clearErrors();
        form.setData({
            action: next,
            paid_at: next === 'pay' ? today() : '',
            reason: '',
        });
        setAction(next);
    }
    function submit(event: FormEvent) {
        event.preventDefault();
        form.patch(change.url(charge.id), {
            preserveScroll: true,
            onSuccess: () => setAction(null),
        });
    }

    return (
        <>
            <Head title="Detalhes da cobrança" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Cobrança simulada
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Este módulo não realiza pagamentos nem integrações
                            bancárias.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={admin ? adminIndex() : residentIndex()}>
                            Voltar ao financeiro
                        </Link>
                    </Button>
                </header>
                <section className="grid gap-3 rounded-xl border p-5">
                    <h2 className="text-lg font-semibold">
                        {charge.description}
                    </h2>
                    <p>{charge.unit}</p>
                    <p>
                        Valor: <strong>{currency(charge.amount_cents)}</strong>
                    </p>
                    <p>Vencimento: {dateText(charge.due_date)}</p>
                    <p>Situação: {statusLabels[charge.status]}</p>
                    {charge.paid_at && (
                        <p>Pagamento simulado: {dateText(charge.paid_at)}</p>
                    )}
                    <div className="flex flex-wrap gap-2">
                        {admin &&
                            ['open', 'overdue'].includes(charge.status) && (
                                <>
                                    <Button onClick={() => choose('pay')}>
                                        Registrar pagamento simulado
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={() => choose('cancel')}
                                    >
                                        Cancelar cobrança
                                    </Button>
                                </>
                            )}
                        {admin && charge.status === 'paid' && (
                            <Button
                                variant="outline"
                                onClick={() => choose('reopen')}
                            >
                                Reabrir cobrança
                            </Button>
                        )}
                        {!admin &&
                            ['open', 'overdue'].includes(charge.status) && (
                                <Button asChild>
                                    <a href={boleto.url(charge.id)}>
                                        Baixar boleto simulado
                                    </a>
                                </Button>
                            )}
                    </div>
                </section>
                {admin && (
                    <section className="grid gap-4 rounded-xl border p-5">
                        <h2 className="text-lg font-semibold">
                            Histórico administrativo
                        </h2>
                        {charge.history?.map((entry, i) => (
                            <article
                                key={i}
                                className="grid gap-1 border-b pb-4 text-sm last:border-0"
                            >
                                <p className="font-medium">
                                    {actions[entry.action] ?? entry.action}
                                </p>
                                <p>
                                    {entry.created_at} · {entry.actor}
                                </p>
                                {entry.reason && <p>Motivo: {entry.reason}</p>}
                                {entry.action === 'updated' && entry.before && (
                                    <p className="text-muted-foreground">
                                        Antes: {entry.before.description} ·{' '}
                                        {currency(entry.before.amount_cents)} ·{' '}
                                        {dateText(entry.before.due_date)} ·
                                        Unidade #{entry.before.unit_id}
                                        <br />
                                        Depois: {entry.after.description} ·{' '}
                                        {currency(entry.after.amount_cents)} ·{' '}
                                        {dateText(entry.after.due_date)} ·
                                        Unidade #{entry.after.unit_id}
                                    </p>
                                )}
                            </article>
                        ))}
                    </section>
                )}
            </div>
            <Dialog
                open={action !== null}
                onOpenChange={(open) => {
                    if (!open && !form.processing) {
                        setAction(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {action === 'pay'
                                ? 'Registrar pagamento simulado'
                                : action === 'cancel'
                                  ? 'Cancelar cobrança'
                                  : 'Reabrir cobrança'}
                        </DialogTitle>
                        <DialogDescription>
                            Esta ação altera apenas os dados simulados do
                            projeto.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="grid gap-4">
                        {action === 'pay' && (
                            <label className="grid gap-2 text-sm">
                                Data do pagamento simulado
                                <input
                                    type="date"
                                    required
                                    max={today()}
                                    className="h-10 rounded-md border bg-background px-3"
                                    value={form.data.paid_at}
                                    onChange={(event) =>
                                        form.setData(
                                            'paid_at',
                                            event.target.value,
                                        )
                                    }
                                />
                            </label>
                        )}
                        {action === 'cancel' && (
                            <label className="grid gap-2 text-sm">
                                Motivo do cancelamento
                                <input
                                    required
                                    maxLength={255}
                                    className="h-10 rounded-md border bg-background px-3"
                                    value={form.data.reason}
                                    onChange={(event) =>
                                        form.setData(
                                            'reason',
                                            event.target.value,
                                        )
                                    }
                                />
                            </label>
                        )}
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
                            {form.processing ? 'Salvando…' : 'Confirmar'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
