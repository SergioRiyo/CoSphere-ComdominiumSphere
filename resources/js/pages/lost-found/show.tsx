import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import LostFoundForm from '@/components/lost-found-form';
import { Button } from '@/components/ui/button';
import { dateText, statusLabels } from '@/types/lost-found';
import type {
    Conversation,
    LostFoundDetails,
    LostFoundItem,
} from '@/types/lost-found';
import {
    index as adminIndex,
    photo as adminPhoto,
    status as adminStatus,
    reply as adminReply,
} from '@/routes/admin/lost-found';
import {
    index as residentIndex,
    photo as residentPhoto,
    status as residentStatus,
    message,
    reply as residentReply,
} from '@/routes/morador/lost-found';

export default function LostFoundShow({ item }: { item: LostFoundDetails }) {
    const admin = usePage().props.auth.user.role === 'admin';
    const [editing, setEditing] = useState(false);
    const stateForm = useForm({ status: item.status });
    const [confirm, setConfirm] = useState<LostFoundItem['status'] | null>(
        null,
    );
    const canEdit = admin || (item.is_author && item.status === 'open');
    function changeStatus() {
        if (!confirm) {
            return;
        }

        stateForm.transform(() => ({ status: confirm }));
        stateForm.patch((admin ? adminStatus : residentStatus).url(item.id), {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    }

    return (
        <>
            <Head title="Detalhes do item" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Detalhes do item
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            A administração pode intermediar o contato sobre
                            este item.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={admin ? adminIndex() : residentIndex()}>
                            Voltar à lista
                        </Link>
                    </Button>
                </header>
                <section className="grid gap-4 rounded-xl border p-5">
                    <div className="flex flex-wrap gap-2 text-sm">
                        <span className="rounded bg-primary/10 px-2 py-1 text-primary">
                            {item.type === 'lost' ? 'Perdido' : 'Encontrado'}
                        </span>
                        <span className="rounded bg-muted px-2 py-1">
                            {statusLabels[item.status]}
                        </span>
                    </div>
                    {item.has_photo && (
                        <img
                            className="max-h-80 max-w-full rounded-lg object-contain"
                            src={
                                admin
                                    ? adminPhoto.url(item.id)
                                    : residentPhoto.url(item.id)
                            }
                            alt="Foto do item"
                        />
                    )}
                    <p className="break-words whitespace-pre-wrap">
                        {item.description}
                    </p>
                    <p className="text-sm">Condição: {item.condition}</p>
                    <p className="text-sm">
                        Local: {item.location} · Data:{' '}
                        {dateText(item.event_date)}
                    </p>
                    {admin && item.author && (
                        <p className="text-sm">
                            Registrado por: {item.author.name}
                            {item.author.unit ? ' · ' + item.author.unit : ''}
                        </p>
                    )}
                    <div className="flex flex-wrap gap-2">
                        {canEdit && (
                            <Button
                                variant="outline"
                                onClick={() => setEditing(true)}
                            >
                                Editar item
                            </Button>
                        )}
                        {admin &&
                            item.status !== 'in_progress' &&
                            item.status !== 'resolved' && (
                                <Button
                                    variant="outline"
                                    onClick={() => setConfirm('in_progress')}
                                >
                                    Iniciar atendimento
                                </Button>
                            )}
                        {admin && item.status !== 'open' && (
                            <Button
                                variant="outline"
                                onClick={() => setConfirm('open')}
                            >
                                Reabrir item
                            </Button>
                        )}
                        {(admin || item.is_author) &&
                            item.status !== 'resolved' && (
                                <Button
                                    variant="outline"
                                    onClick={() => setConfirm('resolved')}
                                >
                                    Finalizar item
                                </Button>
                            )}
                    </div>
                    {confirm && (
                        <div className="flex flex-wrap items-center gap-2 rounded border p-3 text-sm">
                            <span>
                                Confirmar alteração para {statusLabels[confirm]}
                                ?
                            </span>
                            <Button
                                size="sm"
                                disabled={stateForm.processing}
                                onClick={changeStatus}
                            >
                                Confirmar
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => setConfirm(null)}
                            >
                                Voltar
                            </Button>
                        </div>
                    )}
                    {stateForm.hasErrors && (
                        <p role="alert" className="text-sm text-destructive">
                            {Object.values(stateForm.errors).join(' ')}
                        </p>
                    )}
                </section>
                <section className="grid gap-4 rounded-xl border p-5">
                    <div>
                        <h2 className="text-lg font-semibold">
                            Contato com a administração
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Cada manifestação é privada entre o morador e a
                            administração.
                        </p>
                    </div>
                    {item.conversations.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Nenhuma manifestação registrada.
                        </p>
                    )}
                    {item.conversations.map((thread) => (
                        <ConversationView
                            key={thread.id}
                            item={item}
                            conversation={thread}
                            admin={admin}
                        />
                    ))}
                    {!admin &&
                        item.conversations.length === 0 &&
                        item.status !== 'resolved' && (
                            <MessageForm item={item} admin={false} />
                        )}
                    {item.status === 'resolved' && (
                        <p className="text-sm text-muted-foreground">
                            Item finalizado; as conversas permanecem disponíveis
                            para consulta.
                        </p>
                    )}
                </section>
                {item.history && (
                    <section className="grid gap-3 rounded-xl border p-5">
                        <h2 className="text-lg font-semibold">
                            Histórico do item
                        </h2>
                        {item.history.map((event, index) => (
                            <article
                                key={index}
                                className="border-b pb-3 text-sm last:border-0"
                            >
                                <p className="font-medium">
                                    {event.action === 'created'
                                        ? 'Item registrado'
                                        : event.action === 'updated'
                                          ? 'Item editado'
                                          : 'Situação alterada'}
                                </p>
                                <p>
                                    {event.created_at} · {event.actor}
                                </p>
                                {event.action === 'status_changed' && (
                                    <p>
                                        {String(event.before?.status ?? '')} →{' '}
                                        {String(event.after.status ?? '')}
                                    </p>
                                )}
                                {event.action === 'updated' && (
                                    <p className="break-words text-muted-foreground">
                                        Antes:{' '}
                                        {String(
                                            event.before?.description ?? '',
                                        )}{' '}
                                        · {String(event.before?.location ?? '')}{' '}
                                        ·{' '}
                                        {String(event.before?.condition ?? '')}{' '}
                                        ·{' '}
                                        {String(event.before?.event_date ?? '')}
                                        <br />
                                        Depois:{' '}
                                        {String(
                                            event.after.description ?? '',
                                        )}{' '}
                                        · {String(event.after.location ?? '')} ·{' '}
                                        {String(event.after.condition ?? '')} ·{' '}
                                        {String(event.after.event_date ?? '')}
                                    </p>
                                )}
                            </article>
                        ))}
                    </section>
                )}
            </div>
            {canEdit && (
                <LostFoundForm
                    key={String(editing)}
                    item={item}
                    admin={admin}
                    open={editing}
                    onOpenChange={setEditing}
                />
            )}
        </>
    );
}

function ConversationView({
    item,
    conversation,
    admin,
}: {
    item: LostFoundDetails;
    conversation: Conversation;
    admin: boolean;
}) {
    return (
        <article className="grid gap-3 rounded-lg border p-4">
            {admin && (
                <h3 className="font-medium">
                    {conversation.resident}
                    {conversation.unit ? ' · ' + conversation.unit : ''}
                </h3>
            )}
            {conversation.messages.map((entry) => (
                <div key={entry.id} className="rounded bg-muted/50 p-3 text-sm">
                    <p className="font-medium">
                        {entry.sender} · {entry.created_at}
                    </p>
                    <p className="break-words whitespace-pre-wrap">
                        {entry.body}
                    </p>
                </div>
            ))}
            {item.status !== 'resolved' && (
                <MessageForm
                    item={item}
                    admin={admin}
                    conversation={conversation}
                />
            )}
        </article>
    );
}

function MessageForm({
    item,
    admin,
    conversation,
}: {
    item: LostFoundDetails;
    admin: boolean;
    conversation?: Conversation;
}) {
    const form = useForm({ body: '' });
    function submit(event: FormEvent) {
        event.preventDefault();
        const url = conversation
            ? admin
                ? adminReply.url([item.id, conversation.id])
                : residentReply.url([item.id, conversation.id])
            : message.url(item.id);
        form.post(url, { preserveScroll: true, onSuccess: () => form.reset() });
    }

    return (
        <form onSubmit={submit} className="grid gap-2">
            <label className="grid gap-2 text-sm">
                {conversation ? 'Responder' : 'Informar à administração'}
                <textarea
                    required
                    maxLength={2000}
                    className="min-h-20 rounded-md border bg-background p-3"
                    value={form.data.body}
                    onChange={(event) =>
                        form.setData('body', event.target.value)
                    }
                />
            </label>
            {form.hasErrors && (
                <p role="alert" className="text-sm text-destructive">
                    {Object.values(form.errors).join(' ')}
                </p>
            )}
            <Button disabled={form.processing} className="justify-self-start">
                {form.processing ? 'Enviando…' : 'Enviar mensagem'}
            </Button>
        </form>
    );
}
