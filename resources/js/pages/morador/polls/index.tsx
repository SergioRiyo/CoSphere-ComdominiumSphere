import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import ManagementPagination from '@/components/management-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index, show } from '@/routes/morador/polls';
import { formatAnnouncementDate } from '@/types/announcement';
import type { Paged } from '@/types/maintenance';
import { pollStatusLabels } from '@/types/poll';
import type { ResidentPollSummary } from '@/types/poll';

export default function Polls({
    polls,
    state,
    timezone,
}: {
    polls: Paged<ResidentPollSummary>;
    state: 'open' | 'closed';
    timezone: string;
}) {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    return (
        <>
            <Head title="Enquetes" />
            <div className="grid gap-6 p-4 sm:p-6" aria-busy={busy}>
                <header>
                    <h1 className="text-2xl font-semibold">Enquetes</h1>
                    <p className="text-sm text-muted-foreground">
                        Participe das enquetes do condomínio.
                    </p>
                </header>
                <nav
                    aria-label="Situação das enquetes"
                    className="flex flex-wrap gap-2"
                >
                    <Button
                        asChild
                        variant={state === 'open' ? 'default' : 'outline'}
                    >
                        <Link
                            href={index({ query: { state: 'open' } })}
                            aria-current={state === 'open' ? 'page' : undefined}
                        >
                            Abertas
                        </Link>
                    </Button>
                    <Button
                        asChild
                        variant={state === 'closed' ? 'default' : 'outline'}
                    >
                        <Link
                            href={index({ query: { state: 'closed' } })}
                            aria-current={
                                state === 'closed' ? 'page' : undefined
                            }
                        >
                            Encerradas
                        </Link>
                    </Button>
                </nav>
                <p className="text-sm text-muted-foreground">
                    {polls.total} enquete(s)
                </p>
                {error && (
                    <p role="alert" className="text-sm text-destructive">
                        {error}
                    </p>
                )}
                {polls.data.length === 0 && (
                    <p>
                        {polls.total === 0
                            ? `Nenhuma enquete ${state === 'open' ? 'aberta' : 'encerrada'} no momento.`
                            : 'Nenhuma enquete nesta página.'}
                    </p>
                )}
                <div className="grid gap-4 lg:grid-cols-2">
                    {polls.data.map((poll) => (
                        <article
                            key={poll.id}
                            className="grid min-w-0 gap-3 rounded-xl border p-5 break-words"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <h2 className="min-w-0 font-semibold">
                                    {poll.question}
                                </h2>
                                <Badge
                                    variant={
                                        poll.status === 'open'
                                            ? 'default'
                                            : 'outline'
                                    }
                                >
                                    {pollStatusLabels[poll.status]}
                                </Badge>
                            </div>
                            {poll.has_voted && (
                                <p className="text-sm font-medium">
                                    Voto registrado
                                </p>
                            )}
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
                                        {formatAnnouncementDate(
                                            poll.ends_at,
                                            timezone,
                                        )}
                                    </time>
                                </p>
                            )}
                            <Button asChild variant="outline" className="w-fit">
                                <Link href={show(poll.id)}>
                                    {poll.status === 'open' && !poll.has_voted
                                        ? 'Participar'
                                        : 'Consultar enquete'}
                                </Link>
                            </Button>
                        </article>
                    ))}
                </div>
                {polls.last_page > 1 && (
                    <ManagementPagination
                        page={polls.current_page}
                        last={polls.last_page}
                        busy={busy}
                        onChange={(page) => {
                            router.get(
                                index.url(),
                                { state, page },
                                {
                                    preserveScroll: true,
                                    onStart: () => {
                                        setBusy(true);
                                        setError(null);
                                    },
                                    onHttpException: () => {
                                        setError(
                                            'Não foi possível carregar as enquetes.',
                                        );

                                        return false;
                                    },
                                    onNetworkError: () => {
                                        setError(
                                            'Não foi possível conectar ao servidor.',
                                        );

                                        return false;
                                    },
                                    onFinish: () => setBusy(false),
                                },
                            );
                        }}
                    />
                )}
            </div>
        </>
    );
}

Polls.layout = { breadcrumbs: [{ title: 'Enquetes', href: index() }] };
