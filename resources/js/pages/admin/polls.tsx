import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import ManagementPagination from '@/components/management-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/admin/polls';
import { formatAnnouncementDate } from '@/types/announcement';
import type { Paged } from '@/types/maintenance';
import { pollStatusLabels } from '@/types/poll';
import type { PollSummary } from '@/types/poll';

export default function Polls({
    polls,
    timezone,
}: {
    polls: Paged<PollSummary>;
    timezone: string;
}) {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    return (
        <>
            <Head title="Enquetes" />
            <div className="grid gap-6 p-4 sm:p-6" aria-busy={busy}>
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Enquetes</h1>
                        <p className="text-sm text-muted-foreground">
                            Gerencie as enquetes do condomínio.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={create()}>Nova enquete</Link>
                    </Button>
                </header>
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
                            ? 'Nenhuma enquete cadastrada.'
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
                            <p className="text-sm text-muted-foreground">
                                Por {poll.author}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {poll.starts_at
                                    ? `Início: ${formatAnnouncementDate(poll.starts_at, timezone)}`
                                    : 'Ainda não publicada'}
                                {poll.ends_at &&
                                    ` · Encerramento: ${formatAnnouncementDate(poll.ends_at, timezone)}`}
                            </p>
                            <Button asChild variant="outline" className="w-fit">
                                <Link href={show(poll.id)}>
                                    {poll.status === 'draft'
                                        ? 'Editar rascunho'
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
                                { page },
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
