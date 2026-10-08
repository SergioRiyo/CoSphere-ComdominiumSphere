import { Head, Link, router, useHttp } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { IncidentSelect, incidentDate } from '@/components/incident-fields';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    index as adminIndex,
    maintenance,
    priority,
    status,
} from '@/routes/admin/incidents';
import { download } from '@/routes/incident-attachments';
import { index as residentIndex } from '@/routes/morador/incidents';
import type { IncidentDetailsData, IncidentOption } from '@/types/incident';

export default function IncidentDetails({
    incident,
    priorities = [],
    admin = false,
}: {
    incident: IncidentDetailsData;
    priorities?: IncidentOption[];
    admin?: boolean;
}) {
    const lock = useRef(false);
    const [busy, setBusy] = useState(false);
    const statusForm = useHttp<
        { status: string; reason: string },
        { message: string }
    >({ status: '', reason: '' });
    const priorityForm = useHttp<{ priority: string }, { message: string }>({
        priority: incident.priority,
    });
    const maintenanceForm = useHttp<Record<string, never>, { message: string }>(
        {},
    );
    async function act(operation: () => Promise<{ message: string }>) {
        if (lock.current) {
            return;
        }

        lock.current = true;
        setBusy(true);

        try {
            const result = await operation();
            toast.success(result.message);
            statusForm.setData('status', '');
            statusForm.setData('reason', '');
        } catch {
            toast.error(
                'Não foi possível concluir a ação. Confira a mensagem e os dados atualizados.',
            );
        } finally {
            router.reload({
                only: ['incident'],
                onFinish: () => {
                    lock.current = false;
                    setBusy(false);
                },
            });
        }
    }

    return (
        <>
            <Head title={incident.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="grid gap-3">
                    <Button asChild variant="outline" className="w-fit">
                        <Link href={admin ? adminIndex() : residentIndex()}>
                            Voltar à lista
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold break-words">
                        {incident.title}
                    </h1>
                    <div className="flex flex-wrap gap-2">
                        <Badge variant="secondary">
                            {incident.status_label}
                        </Badge>
                        <Badge
                            variant={
                                incident.priority === 'high'
                                    ? 'destructive'
                                    : 'outline'
                            }
                        >
                            Prioridade {incident.priority_label.toLowerCase()}
                        </Badge>
                    </div>
                </header>
                <div className="grid min-w-0 gap-6 xl:grid-cols-3">
                    <div className="grid min-w-0 content-start gap-6 xl:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Resumo</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-3 text-sm">
                                <p>
                                    {incident.type_label} ·{' '}
                                    {incident.category_label}
                                </p>
                                {admin && (
                                    <p className="break-words">
                                        {incident.resident} · {incident.unit}
                                    </p>
                                )}
                                <p>
                                    Abertura: {incidentDate(incident.opened_at)}
                                </p>
                                <p>
                                    Cadastro:{' '}
                                    {incidentDate(incident.created_at)}
                                </p>
                                <h2 className="font-semibold">Descrição</h2>
                                <p className="break-words whitespace-pre-wrap">
                                    {incident.description}
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Anexos</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {incident.attachments.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Nenhum anexo enviado.
                                    </p>
                                ) : (
                                    <ul className="grid gap-3">
                                        {incident.attachments.map(
                                            (attachment) => (
                                                <li
                                                    key={attachment.id}
                                                    className="flex min-w-0 flex-col gap-1 rounded-lg border p-3"
                                                >
                                                    <a
                                                        href={download.url(
                                                            attachment.id,
                                                        )}
                                                        className="text-sm font-medium break-all underline underline-offset-4"
                                                    >
                                                        Baixar {attachment.name}
                                                    </a>
                                                    <span className="text-xs text-muted-foreground">
                                                        {(
                                                            attachment.size /
                                                            1024 /
                                                            1024
                                                        ).toFixed(2)}{' '}
                                                        MiB · acesso privado
                                                    </span>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Histórico</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {incident.history.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Nenhum evento registrado para esta
                                        solicitação legada.
                                    </p>
                                ) : (
                                    <ol className="grid gap-4">
                                        {incident.history.map((event) => (
                                            <li
                                                key={event.key}
                                                className="grid gap-1 border-l-2 pl-4 text-sm"
                                            >
                                                <time className="text-xs text-muted-foreground">
                                                    {incidentDate(
                                                        event.created_at,
                                                    )}
                                                </time>
                                                <p className="font-medium">
                                                    {event.kind === 'priority'
                                                        ? 'Prioridade'
                                                        : event.from_label ===
                                                            null
                                                          ? 'Solicitação registrada'
                                                          : 'Status'}
                                                    :{' '}
                                                    {event.from_label && (
                                                        <>
                                                            {event.from_label}{' '}
                                                            →{' '}
                                                        </>
                                                    )}
                                                    {event.to_label}
                                                </p>
                                                <p>{event.actor}</p>
                                                {event.reason && (
                                                    <p className="break-words whitespace-pre-wrap">
                                                        {event.reason}
                                                    </p>
                                                )}
                                            </li>
                                        ))}
                                    </ol>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                    <div className="grid min-w-0 content-start gap-6">
                        {admin && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Ações administrativas</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-6">
                                    {incident.can_update_priority && (
                                        <form
                                            onSubmit={(event) => {
                                                event.preventDefault();
                                                void act(() =>
                                                    priorityForm.patch(
                                                        priority.url(
                                                            incident.id,
                                                        ),
                                                    ),
                                                );
                                            }}
                                            className="grid gap-3"
                                        >
                                            <IncidentSelect
                                                id="admin-priority"
                                                label="Prioridade"
                                                value={
                                                    priorityForm.data.priority
                                                }
                                                options={priorities}
                                                onChange={(value) =>
                                                    priorityForm.setData(
                                                        'priority',
                                                        value,
                                                    )
                                                }
                                                disabled={busy}
                                                error={
                                                    priorityForm.errors.priority
                                                }
                                            />
                                            <Button
                                                variant="outline"
                                                disabled={
                                                    busy ||
                                                    priorityForm.data
                                                        .priority ===
                                                        incident.priority
                                                }
                                                type="submit"
                                            >
                                                Salvar prioridade
                                            </Button>
                                        </form>
                                    )}
                                    {incident.allowed_statuses.length > 0 ? (
                                        <form
                                            onSubmit={(event) => {
                                                event.preventDefault();
                                                void act(() =>
                                                    statusForm.patch(
                                                        status.url(incident.id),
                                                    ),
                                                );
                                            }}
                                            className="grid gap-3 border-t pt-5"
                                        >
                                            <IncidentSelect
                                                id="admin-status"
                                                label="Novo status"
                                                value={statusForm.data.status}
                                                options={
                                                    incident.allowed_statuses
                                                }
                                                onChange={(value) =>
                                                    statusForm.setData(
                                                        'status',
                                                        value,
                                                    )
                                                }
                                                disabled={busy}
                                                error={statusForm.errors.status}
                                            />
                                            <Label htmlFor="incident-reason">
                                                Motivo (opcional)
                                            </Label>
                                            <Input
                                                id="incident-reason"
                                                maxLength={255}
                                                value={statusForm.data.reason}
                                                onChange={(event) =>
                                                    statusForm.setData(
                                                        'reason',
                                                        event.target.value,
                                                    )
                                                }
                                                disabled={busy}
                                            />
                                            <InputError
                                                message={
                                                    statusForm.errors.reason
                                                }
                                            />
                                            <Button
                                                disabled={
                                                    busy ||
                                                    !statusForm.data.status
                                                }
                                                type="submit"
                                            >
                                                Atualizar status
                                            </Button>
                                        </form>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            Esta solicitação está encerrada. O
                                            status não pode mais ser alterado.
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                        <Card>
                            <CardHeader>
                                <CardTitle>Manutenção vinculada</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                {incident.maintenance_requests.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Nenhuma manutenção vinculada.
                                    </p>
                                ) : (
                                    incident.maintenance_requests.map(
                                        (request) => (
                                            <div
                                                key={request.id}
                                                className="grid gap-2 rounded-lg border p-3 text-sm"
                                            >
                                                <p className="font-medium">
                                                    Manutenção #{request.id}
                                                </p>
                                                <Badge
                                                    variant="secondary"
                                                    className="w-fit"
                                                >
                                                    {request.status_label}
                                                </Badge>
                                                <p className="break-words">
                                                    Prestador:{' '}
                                                    {request.provider ||
                                                        'Ainda não atribuído'}
                                                </p>
                                                <p>
                                                    Registrada:{' '}
                                                    {incidentDate(
                                                        request.created_at,
                                                    )}
                                                </p>
                                                <p>
                                                    Agendamento:{' '}
                                                    {incidentDate(
                                                        request.scheduled_at,
                                                    )}
                                                </p>
                                                <p>
                                                    Execução:{' '}
                                                    {incidentDate(
                                                        request.executed_at,
                                                    )}
                                                </p>
                                            </div>
                                        ),
                                    )
                                )}
                                {admin && incident.can_create_maintenance && (
                                    <Button
                                        disabled={busy}
                                        onClick={() =>
                                            void act(() =>
                                                maintenanceForm.post(
                                                    maintenance.url(
                                                        incident.id,
                                                    ),
                                                ),
                                            )
                                        }
                                    >
                                        Iniciar manutenção
                                    </Button>
                                )}
                                {Object.entries(maintenanceForm.errors).map(
                                    ([key, error]) => (
                                        <InputError key={key} message={error} />
                                    ),
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
