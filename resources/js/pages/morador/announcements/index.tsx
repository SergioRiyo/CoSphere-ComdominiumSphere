import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import ManagementPagination from '@/components/management-pagination';
import { Button } from '@/components/ui/button';
import { index, show } from '@/routes/morador/announcements';
import { formatAnnouncementDate } from '@/types/announcement';
import type { PublishedAnnouncementSummary } from '@/types/announcement';
import type { Paged } from '@/types/maintenance';

export default function Announcements({
    announcements,
    timezone,
}: {
    announcements: Paged<PublishedAnnouncementSummary>;
    timezone: string;
}) {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    return (
        <>
            <Head title="Comunicados" />
            <div className="grid gap-6 p-4 sm:p-6" aria-busy={busy}>
                <header>
                    <h1 className="text-2xl font-semibold">Comunicados</h1>
                    <p className="text-sm text-muted-foreground">
                        Acompanhe os comunicados publicados pelo condomínio.
                    </p>
                </header>
                <p className="text-sm text-muted-foreground">
                    {announcements.total} comunicado(s)
                </p>
                {error && (
                    <p role="alert" className="text-sm text-destructive">
                        {error}
                    </p>
                )}
                {announcements.data.length === 0 && (
                    <p>
                        {announcements.total === 0
                            ? 'Nenhum comunicado publicado.'
                            : 'Nenhum comunicado nesta página.'}
                    </p>
                )}
                <div className="grid gap-4 lg:grid-cols-2">
                    {announcements.data.map((announcement) => (
                        <article
                            key={announcement.id}
                            className="grid min-w-0 gap-3 rounded-xl border p-5 break-words"
                        >
                            <h2 className="font-semibold">
                                {announcement.title}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Por {announcement.author}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Publicado em{' '}
                                <time dateTime={announcement.published_at}>
                                    {formatAnnouncementDate(
                                        announcement.published_at,
                                        timezone,
                                    )}
                                </time>
                            </p>
                            <Button asChild variant="outline" className="w-fit">
                                <Link href={show(announcement.id)}>
                                    Ler comunicado
                                </Link>
                            </Button>
                        </article>
                    ))}
                </div>
                {announcements.last_page > 1 && (
                    <ManagementPagination
                        page={announcements.current_page}
                        last={announcements.last_page}
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
                                            'Não foi possível carregar os comunicados.',
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

Announcements.layout = {
    breadcrumbs: [{ title: 'Comunicados', href: index() }],
};
