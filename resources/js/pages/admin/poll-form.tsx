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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { close, index, publish, store, update } from '@/routes/admin/polls';
import { formatAnnouncementDate } from '@/types/announcement';
import { pollStatusLabels } from '@/types/poll';
import type { Poll, PollResults } from '@/types/poll';

export default function PollForm({
    poll,
    results,
    timezone,
}: {
    poll: Poll | null;
    results: PollResults | null;
    timezone: string;
}) {
    const form = useForm({
        question: poll?.question ?? '',
        options: poll?.options.map((option) => option.text) ?? ['', ''],
    });
    const action = useForm({});
    const [confirm, setConfirm] = useState<'publish' | 'close' | null>(null);
    const [error, setError] = useState<string | null>(null);
    const lock = useRef(false);
    const busy = form.processing || action.processing;
    const draft = !poll || poll.status === 'draft';
    const errors = form.errors as Record<string, string | undefined>;

    return (
        <>
            <Head title={poll ? 'Enquete' : 'Nova enquete'} />
            <div className="grid gap-6 p-4 sm:p-6">
                <Button asChild variant="outline" className="w-fit">
                    <Link href={index()}>Voltar às enquetes</Link>
                </Button>
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="text-2xl font-semibold">
                        {poll ? 'Enquete' : 'Nova enquete'}
                    </h1>
                    <Badge
                        variant={
                            poll?.status === 'open' ? 'default' : 'outline'
                        }
                    >
                        {poll ? pollStatusLabels[poll.status] : 'Rascunho'}
                    </Badge>
                </div>
                {poll && (
                    <p className="text-sm text-muted-foreground">
                        Por {poll.author}
                    </p>
                )}
                <p className="text-sm text-muted-foreground">
                    {poll?.starts_at
                        ? `Início: ${formatAnnouncementDate(poll.starts_at, timezone)}`
                        : 'A votação começa imediatamente após a publicação.'}{' '}
                    {poll?.ends_at
                        ? `Encerramento: ${formatAnnouncementDate(poll.ends_at, timezone)}`
                        : 'O encerramento é feito manualmente pelo administrador.'}
                </p>
                {error && (
                    <p role="alert" className="text-sm text-destructive">
                        {error}
                    </p>
                )}
                {draft ? (
                    <form
                        className="grid max-w-3xl gap-5 rounded-xl border p-5"
                        onSubmit={(event) => {
                            event.preventDefault();

                            if (lock.current) {
                                return;
                            }

                            lock.current = true;
                            setError(null);
                            const options = {
                                preserveScroll: true,
                                onError: () =>
                                    setError('Confira a pergunta e as opções.'),
                                onHttpException: () => {
                                    setError(
                                        'Não foi possível salvar. Seus dados foram preservados.',
                                    );

                                    return false as const;
                                },
                                onNetworkError: () => {
                                    setError(
                                        'Não foi possível conectar ao servidor. Seus dados foram preservados.',
                                    );

                                    return false as const;
                                },
                                onFinish: () => {
                                    lock.current = false;
                                },
                            };

                            if (poll) {
                                form.patch(update.url(poll.id), options);
                            } else {
                                form.post(store.url(), options);
                            }
                        }}
                    >
                        <fieldset
                            disabled={busy}
                            className="grid min-w-0 gap-4"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="question">Pergunta</Label>
                                <Input
                                    id="question"
                                    value={form.data.question}
                                    maxLength={255}
                                    required
                                    aria-invalid={Boolean(form.errors.question)}
                                    onChange={(event) =>
                                        form.setData(
                                            'question',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.question} />
                            </div>
                            <div className="grid gap-3">
                                <p className="text-sm font-medium">
                                    Opções de resposta (mínimo de duas)
                                </p>
                                <InputError message={errors.options} />
                                {form.data.options.map(
                                    (option, optionIndex) => (
                                        <div
                                            key={optionIndex}
                                            className="grid gap-2"
                                        >
                                            <Label
                                                htmlFor={`option-${optionIndex}`}
                                            >
                                                Opção {optionIndex + 1}
                                            </Label>
                                            <div className="flex flex-wrap gap-2 sm:flex-nowrap">
                                                <Input
                                                    id={`option-${optionIndex}`}
                                                    className="min-w-0 flex-1"
                                                    value={option}
                                                    maxLength={255}
                                                    required
                                                    aria-invalid={Boolean(
                                                        errors[
                                                            `options.${optionIndex}`
                                                        ],
                                                    )}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'options',
                                                            form.data.options.map(
                                                                (
                                                                    value,
                                                                    index,
                                                                ) =>
                                                                    index ===
                                                                    optionIndex
                                                                        ? event
                                                                              .target
                                                                              .value
                                                                        : value,
                                                            ),
                                                        )
                                                    }
                                                />
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    disabled={
                                                        form.data.options
                                                            .length <= 2
                                                    }
                                                    aria-label={`Remover opção ${optionIndex + 1}`}
                                                    onClick={() => {
                                                        form.setData(
                                                            'options',
                                                            form.data.options.filter(
                                                                (_, index) =>
                                                                    index !==
                                                                    optionIndex,
                                                            ),
                                                        );
                                                        form.clearErrors();
                                                    }}
                                                >
                                                    Remover
                                                </Button>
                                            </div>
                                            <InputError
                                                message={
                                                    errors[
                                                        `options.${optionIndex}`
                                                    ]
                                                }
                                            />
                                        </div>
                                    ),
                                )}
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="w-fit"
                                    onClick={() =>
                                        form.setData('options', [
                                            ...form.data.options,
                                            '',
                                        ])
                                    }
                                >
                                    Adicionar opção
                                </Button>
                            </div>
                        </fieldset>
                        <div className="flex flex-wrap gap-3">
                            <Button disabled={busy}>
                                {form.processing
                                    ? 'Salvando...'
                                    : 'Salvar rascunho'}
                            </Button>
                            {poll && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={busy || form.isDirty}
                                    onClick={() => {
                                        setError(null);
                                        setConfirm('publish');
                                    }}
                                >
                                    Publicar enquete
                                </Button>
                            )}
                        </div>
                        {poll && form.isDirty && (
                            <p className="text-sm text-muted-foreground">
                                Salve as alterações antes de publicar.
                            </p>
                        )}
                    </form>
                ) : (
                    poll && (
                        <section className="grid max-w-3xl min-w-0 gap-5 rounded-xl border p-5 break-words">
                            <p className="text-sm text-muted-foreground">
                                A pergunta e as opções não podem ser alteradas
                                após a publicação.
                            </p>
                            <h2 className="text-xl font-semibold">
                                {poll.question}
                            </h2>
                            <div className="grid gap-3">
                                <h3 className="font-semibold">Resultados</h3>
                                <p className="text-sm text-muted-foreground">
                                    {results?.total ?? 0} voto(s) registrado(s)
                                </p>
                                {results?.options.map((option) => (
                                    <div
                                        key={option.id}
                                        className="grid gap-1 rounded-lg border p-3"
                                    >
                                        <p className="font-medium">
                                            {option.text}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {option.votes} voto(s) ·{' '}
                                            {new Intl.NumberFormat('pt-BR', {
                                                maximumFractionDigits: 2,
                                            }).format(option.percentage)}
                                            %
                                        </p>
                                    </div>
                                ))}
                            </div>
                            {poll.status === 'open' && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="w-fit"
                                    disabled={busy}
                                    onClick={() => {
                                        setError(null);
                                        setConfirm('close');
                                    }}
                                >
                                    Encerrar enquete
                                </Button>
                            )}
                        </section>
                    )
                )}
                <Dialog
                    open={confirm !== null}
                    onOpenChange={(open) => {
                        if (!open && !busy) {
                            setConfirm(null);
                        }
                    }}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {confirm === 'publish'
                                    ? 'Publicar enquete?'
                                    : 'Encerrar enquete?'}
                            </DialogTitle>
                            <DialogDescription>
                                {confirm === 'publish'
                                    ? 'A votação começa imediatamente. A pergunta e as opções não poderão mais ser alteradas.'
                                    : 'A enquete deixará de receber votos e permanecerá disponível para consulta dos resultados.'}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={busy}
                                onClick={() => setConfirm(null)}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="button"
                                disabled={busy}
                                onClick={() => {
                                    if (
                                        !poll ||
                                        !confirm ||
                                        lock.current ||
                                        (confirm === 'publish' && form.isDirty)
                                    ) {
                                        return;
                                    }

                                    lock.current = true;
                                    setError(null);
                                    const target =
                                        confirm === 'publish'
                                            ? publish.url(poll.id)
                                            : close.url(poll.id);
                                    action.patch(target, {
                                        preserveScroll: true,
                                        onSuccess: () => setConfirm(null),
                                        onError: (errors) => {
                                            setConfirm(null);
                                            setError(
                                                errors.poll ||
                                                    errors.options ||
                                                    'Não foi possível concluir a operação.',
                                            );
                                        },
                                        onHttpException: () => {
                                            setConfirm(null);
                                            setError(
                                                'Não foi possível concluir a operação. Tente novamente.',
                                            );

                                            return false;
                                        },
                                        onNetworkError: () => {
                                            setConfirm(null);
                                            setError(
                                                'Não foi possível conectar ao servidor.',
                                            );

                                            return false;
                                        },
                                        onFinish: () => {
                                            lock.current = false;
                                        },
                                    });
                                }}
                            >
                                {action.processing
                                    ? 'Aguarde...'
                                    : confirm === 'publish'
                                      ? 'Confirmar publicação'
                                      : 'Confirmar encerramento'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

PollForm.layout = { breadcrumbs: [{ title: 'Enquetes', href: index() }] };
