import { Head, router } from '@inertiajs/react';
import { Bell, Check } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes/morador';
import { index, read } from '@/routes/morador/notifications';

type Notification = {
    id: number;
    title: string;
    message: string;
    type: string;
    type_label: string;
    sent_at: string | null;
    is_read: boolean;
};

type NotificationsPageProps = {
    notifications: {
        data: Notification[];
        current_page: number;
        last_page: number;
        total: number;
    };
    timezone: string;
};

export default function NotificationsPage({
    notifications,
    timezone,
}: NotificationsPageProps) {
    const [busy, setBusy] = useState(false);
    const [readingId, setReadingId] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);

    const visitOptions = (message: string) => ({
        preserveScroll: true,
        onStart: () => {
            setBusy(true);
            setError(null);
        },
        onError: () => setError(message),
        onHttpException: () => {
            setError(message);

            return false;
        },
        onNetworkError: () => {
            setError(
                'Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.',
            );

            return false;
        },
        onFinish: () => {
            setBusy(false);
            setReadingId(null);
        },
    });

    const markAsRead = (notification: Notification) => {
        setReadingId(notification.id);
        router.patch(
            read.url(notification.id),
            {},
            visitOptions(
                'Não foi possível marcar a notificação como lida. Tente novamente.',
            ),
        );
    };

    const changePage = (page: number) => {
        router.get(
            index.url(),
            { page },
            {
                ...visitOptions(
                    'Não foi possível carregar as notificações. Tente novamente.',
                ),
                preserveState: true,
            },
        );
    };

    return (
        <>
            <Head title="Notificações" />
            <div
                className="flex flex-1 flex-col gap-6 p-4 sm:p-6"
                aria-busy={busy}
            >
                <header className="flex flex-col gap-2">
                    <h1 className="flex items-center gap-2 text-3xl font-semibold tracking-tight">
                        <Bell className="size-6" aria-hidden="true" />
                        Notificações
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Acompanhe seus avisos de reservas, encomendas e outras
                        atualizações.
                    </p>
                </header>

                {error && (
                    <p
                        role="alert"
                        className="rounded-lg border border-destructive/30 p-4 text-sm text-destructive"
                    >
                        {error}
                    </p>
                )}

                {notifications.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                            <Bell
                                className="size-8 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <p className="font-medium">
                                {notifications.total === 0
                                    ? 'Você ainda não possui notificações.'
                                    : 'Nenhuma notificação nesta página.'}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Seus avisos aparecerão aqui quando forem
                                enviados.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <ul
                        className="flex flex-col gap-4"
                        aria-label="Suas notificações"
                    >
                        {notifications.data.map((notification) => (
                            <li key={notification.id}>
                                <Card
                                    className={
                                        notification.is_read
                                            ? ''
                                            : 'border-primary/40 bg-primary/5'
                                    }
                                >
                                    <CardHeader className="gap-3">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge variant="outline">
                                                {notification.type_label}
                                            </Badge>
                                            <Badge
                                                variant={
                                                    notification.is_read
                                                        ? 'secondary'
                                                        : 'default'
                                                }
                                            >
                                                {notification.is_read
                                                    ? 'Lida'
                                                    : 'Não lida'}
                                            </Badge>
                                        </div>
                                        <CardTitle className="break-words">
                                            {notification.title}
                                        </CardTitle>
                                        {notification.sent_at ? (
                                            <time
                                                dateTime={notification.sent_at}
                                                className="text-sm text-muted-foreground"
                                            >
                                                {new Intl.DateTimeFormat(
                                                    'pt-BR',
                                                    {
                                                        dateStyle: 'short',
                                                        timeStyle: 'short',
                                                        timeZone: timezone,
                                                    },
                                                ).format(
                                                    new Date(
                                                        notification.sent_at,
                                                    ),
                                                )}
                                            </time>
                                        ) : (
                                            <span className="text-sm text-muted-foreground">
                                                Data não informada
                                            </span>
                                        )}
                                    </CardHeader>
                                    <CardContent className="flex flex-col items-start gap-4">
                                        <p className="text-sm break-words whitespace-pre-wrap">
                                            {notification.message}
                                        </p>
                                        {!notification.is_read && (
                                            <Button
                                                variant="outline"
                                                disabled={busy}
                                                onClick={() =>
                                                    markAsRead(notification)
                                                }
                                                aria-label={`Marcar como lida: ${notification.title}`}
                                            >
                                                <Check aria-hidden="true" />
                                                {readingId === notification.id
                                                    ? 'Salvando...'
                                                    : 'Marcar como lida'}
                                            </Button>
                                        )}
                                    </CardContent>
                                </Card>
                            </li>
                        ))}
                    </ul>
                )}

                {notifications.last_page > 1 && (
                    <nav
                        aria-label="Paginação das notificações"
                        className="flex flex-wrap items-center justify-between gap-3"
                    >
                        <p className="text-sm text-muted-foreground">
                            Página {notifications.current_page} de{' '}
                            {notifications.last_page} · {notifications.total}{' '}
                            notificações
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                disabled={
                                    busy || notifications.current_page <= 1
                                }
                                onClick={() =>
                                    changePage(notifications.current_page - 1)
                                }
                            >
                                Anterior
                            </Button>
                            <Button
                                variant="outline"
                                disabled={
                                    busy ||
                                    notifications.current_page >=
                                        notifications.last_page
                                }
                                onClick={() =>
                                    changePage(notifications.current_page + 1)
                                }
                            >
                                Próxima
                            </Button>
                        </div>
                    </nav>
                )}
            </div>
        </>
    );
}

NotificationsPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Notificações', href: index() },
    ],
};
