import { Head, Link, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { index, vote } from '@/routes/morador/polls';
import { formatAnnouncementDate } from '@/types/announcement';
import { pollStatusLabels } from '@/types/poll';
import type { ResidentPoll } from '@/types/poll';

export default function PollDetails({
    poll,
    timezone,
}: {
    poll: ResidentPoll;
    timezone: string;
}) {
    const form = useForm({ option_id: '' });
    const [confirm, setConfirm] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const lock = useRef(false);
    const selected = poll.options.find(
        (option) => String(option.id) === form.data.option_id,
    );
    const canVote = poll.status === 'open' && !poll.has_voted;

    return (
        <>
            <Head title="Enquete" />
            <div className="grid gap-6 p-4 sm:p-6">
                <Button asChild variant="outline" className="w-fit">
                    <Link
                        href={index({
                            query: {
                                state:
                                    poll.status === 'closed'
                                        ? 'closed'
                                        : 'open',
                            },
                        })}
                    >
                        Voltar às enquetes
                    </Link>
                </Button>
                <article className="grid max-w-3xl min-w-0 gap-5 rounded-xl border p-5 break-words">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <h1 className="text-2xl font-semibold">
                            {poll.question}
                        </h1>
                        <Badge
                            variant={
                                poll.status === 'open' ? 'default' : 'outline'
                            }
                        >
                            {pollStatusLabels[poll.status]}
                        </Badge>
                    </div>
                    {poll.starts_at && (
                        <p className="text-sm text-muted-foreground">
                            Início:{' '}
                            <time dateTime={poll.starts_at}>
                                {formatAnnouncementDate(
                                    poll.starts_at,
                                    timezone,
                                )}
                            </time>
                        </p>
                    )}
                    {poll.ends_at && (
                        <p className="text-sm text-muted-foreground">
                            Encerramento:{' '}
                            <time dateTime={poll.ends_at}>
                                {formatAnnouncementDate(poll.ends_at, timezone)}
                            </time>
                        </p>
                    )}
                    {poll.has_voted && (
                        <p
                            role="status"
                            className="rounded-lg border p-4 font-medium"
                        >
                            Voto registrado.
                        </p>
                    )}
                    {!poll.has_voted && poll.status === 'closed' && (
                        <p>A votação foi encerrada.</p>
                    )}
                    {!poll.has_voted && poll.status === 'scheduled' && (
                        <p>A votação ainda não está aberta.</p>
                    )}
                    {error && (
                        <p role="alert" className="text-sm text-destructive">
                            {error}
                        </p>
                    )}
                    {canVote ? (
                        <form
                            className="grid gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();

                                if (selected && !form.processing) {
                                    setError(null);
                                    setConfirm(true);
                                }
                            }}
                        >
                            <fieldset
                                className="grid gap-3"
                                disabled={form.processing}
                            >
                                <legend className="mb-3 font-semibold">
                                    Escolha uma opção
                                </legend>
                                {poll.options.map((option) => (
                                    <label
                                        key={option.id}
                                        className="flex cursor-pointer items-start gap-3 rounded-lg border p-4 has-checked:border-primary has-checked:bg-primary/5"
                                    >
                                        <input
                                            type="radio"
                                            name="option_id"
                                            value={option.id}
                                            required
                                            checked={
                                                form.data.option_id ===
                                                String(option.id)
                                            }
                                            onChange={() =>
                                                form.setData(
                                                    'option_id',
                                                    String(option.id),
                                                )
                                            }
                                            className="mt-1 size-4 accent-primary"
                                        />
                                        <span className="min-w-0 break-words">
                                            {option.text}
                                        </span>
                                    </label>
                                ))}
                                <InputError message={form.errors.option_id} />
                            </fieldset>
                            <Button
                                className="w-fit"
                                disabled={!selected || form.processing}
                            >
                                Revisar voto
                            </Button>
                        </form>
                    ) : (
                        <section>
                            <h2 className="mb-3 font-semibold">Opções</h2>
                            <ul className="grid gap-2">
                                {poll.options.map((option) => (
                                    <li
                                        key={option.id}
                                        className="rounded-lg border p-3"
                                    >
                                        {option.text}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}
                </article>
                <Dialog
                    open={confirm}
                    onOpenChange={(open) => {
                        if (!form.processing) {
                            setConfirm(open);
                        }
                    }}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Confirmar voto?</DialogTitle>
                            <DialogDescription>
                                Seu voto não poderá ser alterado ou excluído
                                após a confirmação.
                            </DialogDescription>
                        </DialogHeader>
                        <p className="break-words">
                            Opção escolhida: <strong>{selected?.text}</strong>
                        </p>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                onClick={() => setConfirm(false)}
                            >
                                Voltar
                            </Button>
                            <Button
                                type="button"
                                disabled={form.processing || !selected}
                                onClick={() => {
                                    if (!selected || lock.current || !canVote) {
                                        return;
                                    }

                                    lock.current = true;
                                    setError(null);
                                    form.post(vote.url(poll.id), {
                                        preserveScroll: true,
                                        onSuccess: () => setConfirm(false),
                                        onError: (errors) => {
                                            setConfirm(false);
                                            setError(
                                                errors.poll ||
                                                    errors.option ||
                                                    errors.option_id ||
                                                    'Não foi possível registrar o voto.',
                                            );
                                        },
                                        onHttpException: () => {
                                            setConfirm(false);
                                            setError(
                                                'Não foi possível registrar o voto. Tente novamente.',
                                            );

                                            return false;
                                        },
                                        onNetworkError: () => {
                                            setConfirm(false);
                                            setError(
                                                'Não foi possível conectar ao servidor. Confira a enquete antes de tentar novamente.',
                                            );

                                            return false;
                                        },
                                        onFinish: () => {
                                            lock.current = false;
                                        },
                                    });
                                }}
                            >
                                {form.processing
                                    ? 'Registrando...'
                                    : 'Confirmar voto'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

PollDetails.layout = { breadcrumbs: [{ title: 'Enquetes', href: index() }] };
