import { Head, Link, usePage, usePoll } from '@inertiajs/react';
import {
    ArrowRight,
    ClipboardCheck,
    History,
    Package,
    PackageCheck,
    PackageOpen,
    UsersRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { DashboardWelcomeCard } from '@/components/dashboard-welcome-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes/portaria';
import { index as orderHistory } from '@/routes/portaria/order-history';
import { index as ordersIndex } from '@/routes/portaria/orders';
import { index as visitorAccessHistory } from '@/routes/portaria/visitor-access-history';
import { index as visitorAccesses } from '@/routes/portaria/visitor-accesses';
import { validation } from '@/routes/portaria/visitor-authorizations';

type Unit = { block: string | null; number: string } | null;
type Overview = {
    waiting_orders: number;
    pickup_orders: number;
    recent_orders: {
        id: number;
        description: string | null;
        status: string;
        unit: Unit;
        date: string | null;
    }[];
    recent_accesses: {
        id: number;
        visitor: string;
        status: string;
        unit: Unit;
        date: string | null;
    }[];
};

function SummaryCard({
    title,
    value,
    detail,
    icon: Icon,
    href,
}: {
    title: string;
    value: number;
    detail: string;
    icon: LucideIcon;
    href: string;
}) {
    return (
        <Card className="gap-4 border-cosphere-line/80 py-5 shadow-sm dark:border-border">
            <CardHeader className="flex-row items-center justify-between gap-3">
                <CardTitle>{title}</CardTitle>
                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <Icon className="size-5" aria-hidden="true" />
                </span>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col gap-2">
                <p className="text-3xl font-semibold tabular-nums">{value}</p>
                <p className="min-h-10 text-sm text-muted-foreground">
                    {detail}
                </p>
                <Link
                    href={href}
                    className="mt-auto inline-flex w-fit items-center gap-1 text-sm font-medium text-cosphere-blue hover:underline dark:text-foreground"
                >
                    Ver detalhes{' '}
                    <ArrowRight className="size-4" aria-hidden="true" />
                </Link>
            </CardContent>
        </Card>
    );
}

const unitLabel = (unit: Unit) =>
    unit
        ? [unit.block ? `Bloco ${unit.block}` : null, `Unidade ${unit.number}`]
              .filter(Boolean)
              .join(' · ')
        : 'Unidade não informada';

export default function PortariaDashboard({
    present_visitors: presentVisitors,
    overview,
    timezone,
}: {
    present_visitors: number;
    overview: Overview;
    timezone: string;
}) {
    const { auth } = usePage().props;
    usePoll(30_000, { only: ['present_visitors', 'overview'] });
    const formatDate = (value: string | null) =>
        value
            ? new Intl.DateTimeFormat('pt-BR', {
                  dateStyle: 'short',
                  timeStyle: 'short',
                  timeZone: timezone,
              }).format(new Date(value))
            : 'Data não informada';

    return (
        <>
            <Head title="Dashboard da portaria" />
            <div className="flex h-full flex-1 flex-col p-4 sm:p-6">
                <main className="mx-auto flex w-full max-w-7xl flex-col gap-6">
                    <DashboardWelcomeCard
                        user={auth.user}
                        roleLabel="Porteiro"
                        description="Acompanhe a movimentação e as atividades da portaria."
                    />
                    <section
                        aria-label="Resumo da portaria"
                        className="grid gap-4 md:grid-cols-3"
                    >
                        <SummaryCard
                            title="Visitantes presentes"
                            value={presentVisitors}
                            detail="Entradas registradas sem saída."
                            icon={UsersRound}
                            href={visitorAccesses.url()}
                        />
                        <SummaryCard
                            title="Encomendas aguardadas"
                            value={overview.waiting_orders}
                            detail="Encomendas previamente informadas pelos moradores."
                            icon={PackageOpen}
                            href={ordersIndex.url()}
                        />
                        <SummaryCard
                            title="Aguardando retirada"
                            value={overview.pickup_orders}
                            detail="Encomendas recebidas disponíveis para retirada."
                            icon={PackageCheck}
                            href={ordersIndex.url()}
                        />
                    </section>
                    <Card className="border-cosphere-line/80 shadow-sm dark:border-border">
                        <CardHeader>
                            <CardTitle>Ações rápidas</CardTitle>
                            <CardDescription>
                                Acesse as principais atividades da portaria.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-2">
                            <Button asChild>
                                <Link href={validation()}>
                                    <ClipboardCheck /> Validar visitante
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={ordersIndex()}>
                                    <Package /> Receber encomendas
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={visitorAccesses()}>
                                    <UsersRound /> Ver presentes
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={visitorAccessHistory()}>
                                    <History /> Histórico de acessos
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={orderHistory()}>
                                    <History /> Histórico de encomendas
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                    <div className="grid gap-6 lg:grid-cols-2">
                        <Card className="border-cosphere-line/80 shadow-sm dark:border-border">
                            <CardHeader>
                                <CardTitle>Acessos recentes</CardTitle>
                                <CardDescription>
                                    Últimas entradas, saídas e tentativas de
                                    acesso.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {overview.recent_accesses.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Nenhuma movimentação recente.
                                    </p>
                                ) : (
                                    <ul className="divide-y">
                                        {overview.recent_accesses.map(
                                            (access) => (
                                                <li
                                                    key={access.id}
                                                    className="flex flex-wrap items-start justify-between gap-2 py-3 first:pt-0"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="font-medium break-words">
                                                            {access.visitor}
                                                        </p>
                                                        <p className="text-sm text-muted-foreground">
                                                            {unitLabel(
                                                                access.unit,
                                                            )}{' '}
                                                            ·{' '}
                                                            {formatDate(
                                                                access.date,
                                                            )}
                                                        </p>
                                                    </div>
                                                    <Badge variant="secondary">
                                                        {access.status}
                                                    </Badge>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                                <Button
                                    className="mt-4"
                                    variant="outline"
                                    asChild
                                >
                                    <Link href={visitorAccessHistory()}>
                                        Ver histórico de acessos
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                        <Card className="border-cosphere-line/80 shadow-sm dark:border-border">
                            <CardHeader>
                                <CardTitle>Encomendas recentes</CardTitle>
                                <CardDescription>
                                    Últimas atualizações de encomendas.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {overview.recent_orders.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Nenhuma encomenda recente.
                                    </p>
                                ) : (
                                    <ul className="divide-y">
                                        {overview.recent_orders.map((order) => (
                                            <li
                                                key={order.id}
                                                className="flex flex-wrap items-start justify-between gap-2 py-3 first:pt-0"
                                            >
                                                <div className="min-w-0">
                                                    <p className="font-medium break-words">
                                                        Encomenda #{order.id}
                                                        {order.description
                                                            ? ` · ${order.description}`
                                                            : ''}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {unitLabel(order.unit)}{' '}
                                                        ·{' '}
                                                        {formatDate(order.date)}
                                                    </p>
                                                </div>
                                                <Badge variant="secondary">
                                                    {order.status}
                                                </Badge>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                <Button
                                    className="mt-4"
                                    variant="outline"
                                    asChild
                                >
                                    <Link href={orderHistory()}>
                                        Ver histórico de encomendas
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </main>
            </div>
        </>
    );
}

PortariaDashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
