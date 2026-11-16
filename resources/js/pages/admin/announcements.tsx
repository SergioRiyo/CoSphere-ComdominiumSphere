import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import ManagementPagination from '@/components/management-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/admin/announcements';
import { formatAnnouncementDate } from '@/types/announcement';
import type { AnnouncementSummary } from '@/types/announcement';
import type { Paged } from '@/types/maintenance';

export default function Announcements({
    announcements,
    timezone,
}: {
    announcements: Paged<AnnouncementSummary>;
    timezone: string;
}) {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    return (
        <>
            <Head title="Comunicados" />
            <div className="grid gap-6 p-4 sm:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Comunicados</h1>
                        <p className="text-sm text-muted-foreground">
                            Gerencie os comunicados do condomínio.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={create()}>Novo comunicado</Link>
                    </Button>
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
                    <p>Nenhum comunicado cadastrado.</p>
                )}
                <div className="grid gap-4 lg:grid-cols-2">
                    {announcements.data.map((announcement) => (
                        <article
                            key={announcement.id}
                            className="grid min-w-0 gap-3 rounded-xl border p-5 break-words"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <h2 className="min-w-0 font-semibold">
                                    {announcement.title}
                                </h2>
                                <Badge
                                    variant={
                                        announcement.published_at
                                            ? 'default'
                                            : 'outline'
                                    }
                                >
                                    {announcement.published_at
                                        ? 'Publicado'
                                        : 'Rascunho'}
                                </Badge>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                Por {announcement.author}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {announcement.published_at
                                    ? 'Publicado em'
                                    : 'Criado em'}{' '}
                                {formatAnnouncementDate(
                                    announcement.published_at ??
                                        announcement.created_at,
                                    timezone,
                                )}
                            </p>
                            <Button asChild variant="outline" className="w-fit">
                                <Link href={show(announcement.id)}>
                                    {announcement.published_at
                                        ? 'Consultar'
                                        : 'Editar rascunho'}
                                </Link>
                            </Button>
                        </article>
                    ))}
                </div>
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
            </div>
        </>
    );
}

Announcements.layout = {
    breadcrumbs: [{ title: 'Comunicados', href: index() }],
};
