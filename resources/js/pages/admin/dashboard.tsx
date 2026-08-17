import { Head, usePage } from '@inertiajs/react';
import {
    Building2,
    DoorOpen,
    ShieldCheck,
    UserCheck,
    Users,
    UserX,
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
import type { AdminDashboardMetrics } from '@/types';

type AdminDashboardProps = {
    metrics: AdminDashboardMetrics;
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

export default function AdminDashboard({ metrics }: AdminDashboardProps) {
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
