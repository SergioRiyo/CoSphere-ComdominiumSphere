import { Head, Link, router, useHttp } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import ReservationFiltersForm from '@/components/reservation-filters';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    approve,
    reject,
    cancel as adminCancel,
    index as adminIndex,
    show as adminShow,
} from '@/routes/admin/reservations';
import { index as areasIndex } from '@/routes/morador/common-areas';
import {
    cancel as residentCancel,
    index as residentIndex,
    show as residentShow,
} from '@/routes/morador/reservations';
import type {
    OperationalReservation,
    ReservationListProps,
} from '@/types/reservation';

type Action = 'approve' | 'reject' | 'cancel';
const actionLabels: Record<Action, string> = {
    approve: 'Aprovar',
    reject: 'Recusar',
    cancel: 'Cancelar',
};

export default function ReservationList({
    reservations,
    filters,
    statuses,
    admin = false,
}: ReservationListProps & {
    admin?: boolean;
}) {
    const [selection, setSelection] = useState<{
        reservation: OperationalReservation;
        action: Action;
    } | null>(null);
    const [busy, setBusy] = useState(false);
    const submitting = useRef(false);
    const form = useHttp<{ rejection_reason: string }, { message: string }>({
        rejection_reason: '',
    });
    const index = admin ? adminIndex : residentIndex;

    function choose(reservation: OperationalReservation, action: Action) {
        form.clearErrors();
        form.setData('rejection_reason', '');
        setSelection({ reservation, action });
    }

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!selection || submitting.current) {
            return;
        }

        submitting.current = true;
        setBusy(true);
        const { reservation, action } = selection;
        const endpoint =
            action === 'approve'
                ? approve(reservation.id)
                : action === 'reject'
                  ? reject(reservation.id)
                  : admin
                    ? adminCancel(reservation.id)
                    : residentCancel(reservation.id);
        let validationFailed = false;
        form.transform((data) => (action === 'reject' ? data : {}));

        try {
            const response = await form.patch(endpoint.url, {
                onError: (errors) => {
                    validationFailed = true;
                    toast.error(
                        Object.values(errors)[0] ||
                            'Não foi possível alterar a reserva.',
                    );

                    if (!errors.rejection_reason) {
                        setSelection(null);
                    }
                },
            });
            toast.success(response.message);
            setSelection(null);
        } catch {
            if (!validationFailed) {
                toast.error(
                    'Não foi possível alterar a reserva. Atualize a lista e tente novamente.',
                );
                setSelection(null);
            }
        } finally {
            router.reload({
                only: ['reservations'],
                onFinish: () => {
                    setBusy(false);
                    submitting.current = false;
                },
            });
        }
    }

    return (
        <>
            <Head title={admin ? 'Gerenciar reservas' : 'Minhas reservas'} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="grid gap-2">
                        <h1 className="text-2xl font-semibold">
                            {admin ? 'Gerenciar reservas' : 'Minhas reservas'}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {admin
                                ? 'Consulte reservas atuais e anteriores e gerencie as pendências.'
                                : 'Acompanhe suas reservas e o histórico de status. As mais recentes aparecem primeiro.'}
                        </p>
                    </div>
                    {!admin && (
                        <Button asChild variant="outline">
                            <Link href={areasIndex()}>Consultar áreas</Link>
                        </Button>
                    )}
                </header>
                <ReservationFiltersForm
                    key={JSON.stringify(filters)}
                    filters={filters}
                    statuses={statuses}
                    admin={admin}
                    busy={busy}
                    onBusy={setBusy}
                />
                <div aria-live="polite" aria-busy={busy} className="grid gap-4">
                    {busy && (
                        <p
                            role="status"
                            className="text-sm text-muted-foreground"
                        >
                            Atualizando reservas…
                        </p>
                    )}
                    {reservations.data.length === 0 ? (
                        <Card>
                            <CardContent className="py-10 text-center">
                                {Object.values(filters).some(Boolean)
                                    ? 'Nenhuma reserva encontrada para os filtros selecionados.'
                                    : 'Nenhuma reserva encontrada.'}
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="grid gap-4 lg:grid-cols-2">
                            {reservations.data.map((reservation) => (
                                <Card key={reservation.id} className="min-w-0">
                                    <CardHeader className="flex flex-wrap items-start justify-between gap-2">
                                        <CardTitle className="break-words">
                                            {reservation.area}
                                        </CardTitle>
                                        <Badge
                                            variant={
                                                reservation.status === 'pending'
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {reservation.status_label}
                                        </Badge>
                                    </CardHeader>
                                    <CardContent className="grid gap-4 text-sm">
                                        <p>
                                            {reservation.date
                                                .split('-')
                                                .reverse()
                                                .join('/')}{' '}
                                            · {reservation.start}–
                                            {reservation.end}
                                        </p>
                                        {admin && (
                                            <div>
                                                <p className="break-words">
                                                    Morador:{' '}
                                                    {reservation.resident}
                                                </p>
                                                <p>
                                                    {reservation.unit?.block
                                                        ? `Bloco ${reservation.unit.block} · `
                                                        : ''}
                                                    Unidade{' '}
                                                    {reservation.unit?.number}
                                                </p>
                                            </div>
                                        )}
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                asChild
                                                variant="outline"
                                                disabled={busy}
                                            >
                                                <Link
                                                    onBefore={() => !busy}
                                                    href={
                                                        admin
                                                            ? adminShow(
                                                                  reservation.id,
                                                              )
                                                            : residentShow(
                                                                  reservation.id,
                                                              )
                                                    }
                                                >
                                                    Ver detalhes
                                                </Link>
                                            </Button>
                                            {admin &&
                                                reservation.status ===
                                                    'pending' && (
                                                    <>
                                                        <Button
                                                            disabled={busy}
                                                            onClick={() =>
                                                                choose(
                                                                    reservation,
                                                                    'approve',
                                                                )
                                                            }
                                                        >
                                                            Aprovar
                                                        </Button>
                                                        <Button
                                                            variant="outline"
                                                            disabled={busy}
                                                            onClick={() =>
                                                                choose(
                                                                    reservation,
                                                                    'reject',
                                                                )
                                                            }
                                                        >
                                                            Recusar
                                                        </Button>
                                                    </>
                                                )}
                                            {reservation.can_cancel && (
                                                <Button
                                                    variant="destructive"
                                                    disabled={busy}
                                                    onClick={() =>
                                                        choose(
                                                            reservation,
                                                            'cancel',
                                                        )
                                                    }
                                                >
                                                    Cancelar
                                                </Button>
                                            )}
                                            {!admin &&
                                                [
                                                    'pending',
                                                    'confirmed',
                                                ].includes(
                                                    reservation.status,
                                                ) &&
                                                !reservation.can_cancel && (
                                                    <p className="text-muted-foreground">
                                                        O prazo para
                                                        cancelamento pelo
                                                        morador encerrou.
                                                    </p>
                                                )}
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>
                <nav
                    aria-label="Paginação de reservas"
                    className="flex flex-wrap items-center gap-3"
                >
                    {reservations.current_page > 1 && (
                        <Button variant="outline" asChild disabled={busy}>
                            <Link
                                onBefore={() => !busy}
                                href={index({
                                    query: {
                                        ...filters,
                                        page: reservations.current_page - 1,
                                    },
                                })}
                            >
                                Anterior
                            </Link>
                        </Button>
                    )}
                    <span className="text-sm text-muted-foreground">
                        Página {reservations.current_page} de{' '}
                        {reservations.last_page}
                    </span>
                    {reservations.current_page < reservations.last_page && (
                        <Button variant="outline" asChild disabled={busy}>
                            <Link
                                onBefore={() => !busy}
                                href={index({
                                    query: {
                                        ...filters,
                                        page: reservations.current_page + 1,
                                    },
                                })}
                            >
                                Próxima
                            </Link>
                        </Button>
                    )}
                </nav>
            </div>
            <Dialog
                open={selection !== null}
                onOpenChange={(open) => {
                    if (!open && !busy) {
                        setSelection(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {selection ? actionLabels[selection.action] : ''}{' '}
                            reserva?
                        </DialogTitle>
                        <DialogDescription>
                            {selection?.reservation.area} ·{' '}
                            {selection?.reservation.date
                                .split('-')
                                .reverse()
                                .join('/')}{' '}
                            · {selection?.reservation.start}–
                            {selection?.reservation.end}. Confirme para
                            continuar.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="grid gap-4">
                        {selection?.action === 'reject' && (
                            <div className="grid gap-2">
                                <Label htmlFor="rejection-reason">
                                    Motivo da recusa
                                </Label>
                                <Input
                                    id="rejection-reason"
                                    required
                                    maxLength={255}
                                    value={form.data.rejection_reason}
                                    disabled={busy}
                                    onChange={(event) =>
                                        form.setData(
                                            'rejection_reason',
                                            event.target.value,
                                        )
                                    }
                                />
                                {form.errors.rejection_reason && (
                                    <p
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {form.errors.rejection_reason}
                                    </p>
                                )}
                            </div>
                        )}
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={busy}
                                onClick={() => setSelection(null)}
                            >
                                Voltar
                            </Button>
                            <Button
                                type="submit"
                                variant={
                                    selection?.action === 'approve'
                                        ? 'default'
                                        : 'destructive'
                                }
                                disabled={busy}
                            >
                                {busy ? 'Processando…' : 'Confirmar'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
