import { Head, Link, usePage } from '@inertiajs/react';
import {
    Building2,
    DoorOpen,
    ShieldCheck,
    UserCheck,
    Users,
    UserX,
    CalendarDays,
    ClipboardList,
    Megaphone,
    FileChartColumn,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { DashboardWelcomeCard } from '@/components/dashboard-welcome-card';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';
import { dashboard } from '@/routes/admin';
import { create as announcementCreate } from '@/routes/admin/announcements';
import { index as incidentsIndex } from '@/routes/admin/incidents';
import { reservations as reportsIndex } from '@/routes/admin/reports';
import {
    index as reservationsIndex,
    show as reservationShow,
} from '@/routes/admin/reservations';
import { index as usersIndex } from '@/routes/admin/users';
import { index as notificationsIndex } from '@/routes/notifications';
import type { AdminDashboardMetrics } from '@/types';

type AdminDashboardProps = {
    metrics: AdminDashboardMetrics;
    overview: {
        pending_reservations: number;
        open_incidents: number;
        upcoming_reservations: {
            id: number;
            area: string | null;
            unit: string | null;
            starts_at: string;
            status: string;
        }[];
        recent_notifications: {
            id: number;
            title: string;
            message: string;
            sent_at: string | null;
            is_read: boolean;
        }[];
    };
};

type MetricCardProps = {
    description: string;
    icon: LucideIcon;
    label: string;
    tone: string;
    value: number;
};

function MetricCard({
    description,
    icon: Icon,
    label,
    tone,
    value,
}: MetricCardProps) {
    return (
        <Card className="border-cosphere-line/80 shadow-sm dark:border-border">
            <CardHeader className="flex-row items-start justify-between gap-4">
                <div className="min-w-0 space-y-1.5">
                    <h3 className="font-medium break-words text-cosphere-navy dark:text-foreground">
                        {label}
                    </h3>
                    <CardDescription className="leading-relaxed break-words">
                        {description}
                    </CardDescription>
                </div>
                <span
                    className={`inline-flex size-11 shrink-0 items-center justify-center rounded-xl ${tone}`}
                >
                    <Icon className="size-5" aria-hidden="true" />
                </span>
            </CardHeader>
            <CardContent>
                <p className="text-3xl font-semibold tracking-tight text-cosphere-navy tabular-nums dark:text-foreground">
                    {value}
                </p>
            </CardContent>
        </Card>
    );
}

export default function AdminDashboard({
    metrics,
    overview,
}: AdminDashboardProps) {
    const { auth } = usePage().props;
    const metricCards: MetricCardProps[] = [
        {
            label: 'Usuários ativos',
            description: 'Perfis com acesso liberado ao CoSphere.',
            value: metrics.active_users,
            icon: UserCheck,
            tone: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        },
        {
            label: 'Usuários inativos',
            description: 'Perfis que estão com o acesso suspenso.',
            value: metrics.inactive_users,
            icon: UserX,
            tone: 'bg-cosphere-orange/10 text-cosphere-orange',
        },
        {
            label: 'Moradores',
            description: 'Usuários cadastrados com o perfil Morador.',
            value: metrics.residents,
            icon: Users,
            tone: 'bg-cosphere-blue/10 text-cosphere-blue',
        },
        {
            label: 'Porteiros',
            description: 'Usuários cadastrados com o perfil Porteiro.',
            value: metrics.doormen,
            icon: DoorOpen,
            tone: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
        },
        {
            label: 'Administradores',
            description: 'Usuários com acesso à área administrativa.',
            value: metrics.administrators,
            icon: ShieldCheck,
            tone: 'bg-cosphere-navy/10 text-cosphere-navy dark:bg-foreground/10 dark:text-foreground',
        },
        {
            label: 'Unidades',
            description: 'Total de unidades registradas no condomínio.',
            value: metrics.units,
            icon: Building2,
            tone: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
        },
    ];

    return (
        <>
            <Head title="Dashboard administrativo" />
            <div className="flex h-full flex-1 flex-col p-4 sm:p-6">
                <main className="mx-auto flex w-full max-w-6xl flex-col gap-6">
                    <DashboardWelcomeCard
                        user={auth.user}
                        roleLabel="Administrador"
                        description="Acompanhe os principais indicadores de usuários e unidades do condomínio."
                    />

                    <section
                        aria-label="Indicadores principais"
                        className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                    >
                        {[
                            {
                                label: 'Reservas pendentes',
                                value: overview.pending_reservations,
                                icon: CalendarDays,
                                href: reservationsIndex(),
                                tone: 'text-blue-600 bg-blue-500/10',
                            },
                            {
                                label: 'Ocorrências abertas',
                                value: overview.open_incidents,
                                icon: ClipboardList,
                                href: incidentsIndex(),
                                tone: 'text-orange-600 bg-orange-500/10',
                            },
                            {
                                label: 'Usuários ativos',
                                value: metrics.active_users,
                                icon: UserCheck,
                                href: usersIndex(),
                                tone: 'text-emerald-600 bg-emerald-500/10',
                            },
                            {
                                label: 'Usuários inativos',
                                value: metrics.inactive_users,
                                icon: UserX,
                                href: usersIndex(),
                                tone: 'text-sky-600 bg-sky-500/10',
                            },
                        ].map(({ label, value, icon: Icon, href, tone }) => (
                            <Link
                                key={label}
                                href={href}
                                className="flex items-center gap-4 rounded-xl border bg-card p-5 shadow-sm transition-colors hover:bg-accent"
                            >
                                <span
                                    className={`grid size-11 shrink-0 place-items-center rounded-xl ${tone}`}
                                >
                                    <Icon
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <span>
                                    <span className="block text-sm text-muted-foreground">
                                        {label}
                                    </span>
                                    <strong className="text-2xl tabular-nums">
                                        {value}
                                    </strong>
                                </span>
                            </Link>
                        ))}
                    </section>

                    <div className="grid gap-4 xl:grid-cols-3">
                        <Card className="gap-3 p-5">
                            <h2 className="font-semibold">Atalhos rápidos</h2>
                            <div className="grid grid-cols-2 gap-2">
                                {[
                                    {
                                        label: 'Ver reservas',
                                        icon: CalendarDays,
                                        href: reservationsIndex(),
                                    },
                                    {
                                        label: 'Gerenciar usuários',
                                        icon: Users,
                                        href: usersIndex(),
                                    },
                                    {
                                        label: 'Criar comunicado',
                                        icon: Megaphone,
                                        href: announcementCreate(),
                                    },
                                    {
                                        label: 'Ver relatórios',
                                        icon: FileChartColumn,
                                        href: reportsIndex(),
                                    },
                                ].map(({ label, icon: Icon, href }) => (
                                    <Link
                                        key={label}
                                        href={href}
                                        className="flex min-h-20 flex-col items-center justify-center gap-2 rounded-lg border p-3 text-center text-sm transition-colors hover:bg-accent"
                                    >
                                        <Icon
                                            className="size-5 text-cosphere-blue"
                                            aria-hidden="true"
                                        />
                                        {label}
                                    </Link>
                                ))}
                            </div>
                        </Card>
                        <Card className="gap-3 p-5">
                            <div className="flex items-center justify-between gap-2">
                                <h2 className="font-semibold">
                                    Últimas notificações
                                </h2>
                                <Link
                                    href={notificationsIndex()}
                                    className="text-sm text-cosphere-blue"
                                >
                                    Ver todas
                                </Link>
                            </div>
                            {overview.recent_notifications.length ? (
                                <ul className="divide-y">
                                    {overview.recent_notifications.map(
                                        (item) => (
                                            <li key={item.id} className="py-3">
                                                <p className="text-sm font-medium">
                                                    {item.title}
                                                    {!item.is_read && (
                                                        <span
                                                            className="ml-2 inline-block size-2 rounded-full bg-cosphere-orange"
                                                            aria-label="Não lida"
                                                        />
                                                    )}
                                                </p>
                                                <p className="line-clamp-2 text-xs text-muted-foreground">
                                                    {item.message}
                                                </p>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            ) : (
                                <p className="py-4 text-sm text-muted-foreground">
                                    Nenhuma notificação por enquanto.
                                </p>
                            )}
                        </Card>
                        <Card className="gap-3 p-5">
                            <div className="flex items-center justify-between gap-2">
                                <h2 className="font-semibold">
                                    Próximas reservas
                                </h2>
                                <Link
                                    href={reservationsIndex()}
                                    className="text-sm text-cosphere-blue"
                                >
                                    Ver reservas
                                </Link>
                            </div>
                            {overview.upcoming_reservations.length ? (
                                <ul className="divide-y">
                                    {overview.upcoming_reservations.map(
                                        (item) => (
                                            <li key={item.id}>
                                                <Link
                                                    href={reservationShow(
                                                        item.id,
                                                    )}
                                                    className="flex items-center justify-between gap-3 py-3 text-sm"
                                                >
                                                    <span>
                                                        <span className="block font-medium">
                                                            {item.area ??
                                                                'Área não identificada'}
                                                        </span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {new Intl.DateTimeFormat(
                                                                'pt-BR',
                                                                {
                                                                    dateStyle:
                                                                        'short',
                                                                    timeStyle:
                                                                        'short',
                                                                },
                                                            ).format(
                                                                new Date(
                                                                    item.starts_at,
                                                                ),
                                                            )}
                                                            {item.unit
                                                                ? ` · ${item.unit}`
                                                                : ''}
                                                        </span>
                                                    </span>
                                                    <Badge variant="secondary">
                                                        {item.status}
                                                    </Badge>
                                                </Link>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            ) : (
                                <p className="py-4 text-sm text-muted-foreground">
                                    Nenhuma reserva futura.
                                </p>
                            )}
                        </Card>
                    </div>

                    <section
                        aria-labelledby="overview-title"
                        className="space-y-4"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2
                                    id="overview-title"
                                    className="text-xl font-semibold tracking-tight text-cosphere-navy dark:text-foreground"
                                >
                                    Visão geral
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Dados atuais dos cadastros do CoSphere.
                                </p>
                            </div>
                            <Badge
                                variant="outline"
                                className="border-cosphere-blue/30 bg-cosphere-blue/5 text-cosphere-blue"
                            >
                                Dados do sistema
                            </Badge>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {metricCards.map((metric) => (
                                <MetricCard key={metric.label} {...metric} />
                            ))}
                        </div>
                    </section>
                </main>
            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
