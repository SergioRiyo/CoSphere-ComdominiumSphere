import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Building2,
    CalendarDays,
    ClipboardList,
    ContactRound,
    Megaphone,
    Package,
    Plus,
    Vote,
    Wallet,
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
} from '@/components/ui/card';
import { dashboard } from '@/routes/morador';
import {
    index as announcementsIndex,
    show as announcementShow,
} from '@/routes/morador/announcements';
import { index as financialIndex } from '@/routes/morador/financial';
import {
    index as incidentsIndex,
    create as incidentCreate,
} from '@/routes/morador/incidents';
import { index as ordersIndex } from '@/routes/morador/orders';
import { index as pollsIndex, show as pollShow } from '@/routes/morador/polls';
import { index as reservationsIndex } from '@/routes/morador/reservations';
import { index as visitorsIndex } from '@/routes/morador/visitors';
import type { UnitSummary } from '@/types';
import { formatAnnouncementDate } from '@/types/announcement';

type Overview = {
    orders_pending: number | null;
    reservations_count: number;
    next_reservation: {
        id: number;
        area: string | null;
        starts_at: string;
        status: string;
    } | null;
    open_amount_cents: number | null;
    open_charges_count: number | null;
    incidents_open: number;
    announcements: { id: number; title: string; published_at: string }[];
    polls: {
        id: number;
        question: string;
        ends_at: string | null;
        has_voted: boolean;
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
    value: string | number;
    detail: string;
    icon: LucideIcon;
    href: string;
}) {
    return (
        <Card className="gap-4 border-cosphere-line/80 py-5 shadow-sm dark:border-border">
            <CardHeader className="flex-row items-center justify-between gap-3">
                <h3 className="font-medium">{title}</h3>
                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <Icon className="size-5" aria-hidden="true" />
                </span>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col gap-2">
                <p className="text-2xl font-semibold tracking-tight tabular-nums">
                    {value}
                </p>
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

export default function MoradorDashboard({
    unit,
    active_authorizations: activeAuthorizations,
    overview,
    timezone,
}: {
    unit: UnitSummary | null;
    active_authorizations: number;
    overview: Overview;
    timezone: string;
}) {
    const { auth } = usePage().props;
    const next = overview.next_reservation;
    const currency = new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
    const unitLabel = unit
        ? [
              unit.block ? `Bloco ${unit.block}` : null,
              `Unidade ${unit.number}`,
              unit.complement,
          ]
              .filter(Boolean)
              .join(' · ')
        : null;

    return (
        <>
            <Head title="Dashboard do morador" />
            <div className="flex h-full flex-1 flex-col p-4 sm:p-6">
                <main className="mx-auto flex w-full max-w-7xl flex-col gap-6">
                    <DashboardWelcomeCard
                        user={auth.user}
                        roleLabel="Morador"
                        description="Acompanhe sua unidade e as novidades do condomínio em um só lugar."
                    />
                    <div className="flex flex-wrap items-center gap-2 rounded-xl border border-cosphere-line/80 bg-card px-4 py-3 text-sm dark:border-border">
                        <Building2
                            className="size-5 text-primary"
                            aria-hidden="true"
                        />
                        {unitLabel ? (
                            <>
                                <span className="font-medium">
                                    Sua unidade:
                                </span>
                                <span>{unitLabel}</span>
                            </>
                        ) : (
                            <span>
                                Unidade não vinculada. Entre em contato com a
                                administração.
                            </span>
                        )}
                    </div>

                    <section
                        aria-labelledby="overview-title"
                        className="space-y-4"
                    >
                        <div>
                            <h2
                                id="overview-title"
                                className="text-lg font-semibold"
                            >
                                Seu resumo
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Acompanhe o que precisa da sua atenção.
                            </p>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <SummaryCard
                                title="Encomendas"
                                icon={Package}
                                href={ordersIndex.url()}
                                value={overview.orders_pending ?? '—'}
                                detail={
                                    overview.orders_pending === null
                                        ? 'Vincule uma unidade para consultar.'
                                        : overview.orders_pending === 0
                                          ? 'Nenhuma encomenda aguardando retirada.'
                                          : 'Aguardando retirada na portaria.'
                                }
                            />
                            <SummaryCard
                                title="Próximas reservas"
                                icon={CalendarDays}
                                href={reservationsIndex.url()}
                                value={overview.reservations_count}
                                detail={
                                    next
                                        ? `${next.area ?? 'Área comum'} · ${formatAnnouncementDate(next.starts_at, timezone)} · ${next.status}`
                                        : 'Nenhuma reserva próxima.'
                                }
                            />
                            <SummaryCard
                                title="Financeiro · simulação"
                                icon={Wallet}
                                href={financialIndex.url()}
                                value={
                                    overview.open_amount_cents === null
                                        ? '—'
                                        : currency.format(
                                              overview.open_amount_cents / 100,
                                          )
                                }
                                detail={
                                    overview.open_charges_count === null
                                        ? 'Vincule uma unidade para consultar.'
                                        : overview.open_charges_count === 0
                                          ? 'Nenhuma cobrança em aberto.'
                                          : `${overview.open_charges_count} cobrança(s) em aberto, incluindo vencidas.`
                                }
                            />
                            <SummaryCard
                                title="Solicitações"
                                icon={ClipboardList}
                                href={incidentsIndex.url()}
                                value={overview.incidents_open}
                                detail={
                                    overview.incidents_open === 0
                                        ? 'Nenhuma solicitação em andamento.'
                                        : 'Solicitações abertas ou em andamento.'
                                }
                            />
                            <SummaryCard
                                title="Visitantes"
                                icon={ContactRound}
                                href={visitorsIndex.url()}
                                value={unit ? activeAuthorizations : '—'}
                                detail={
                                    !unit
                                        ? 'Vincule uma unidade para consultar.'
                                        : activeAuthorizations === 0
                                          ? 'Nenhuma autorização ativa na unidade.'
                                          : 'Autorizações ativas da unidade.'
                                }
                            />
                        </div>
                    </section>

                    <section
                        aria-labelledby="actions-title"
                        className="space-y-4"
                    >
                        <h2
                            id="actions-title"
                            className="text-lg font-semibold"
                        >
                            Ações rápidas
                        </h2>
                        <div className="flex flex-wrap gap-3">
                            <Button asChild>
                                <Link href={reservationsIndex()}>
                                    <CalendarDays aria-hidden="true" /> Reservar
                                    área
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={visitorsIndex()}>
                                    <ContactRound aria-hidden="true" />{' '}
                                    Autorizar visitante
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={incidentCreate()}>
                                    <Plus aria-hidden="true" /> Registrar
                                    solicitação
                                </Link>
                            </Button>
                        </div>
                    </section>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <Card className="border-cosphere-line/80 shadow-sm dark:border-border">
                            <CardHeader>
                                <div className="flex items-center gap-2">
                                    <Megaphone
                                        className="size-5 text-primary"
                                        aria-hidden="true"
                                    />
                                    <h2 className="text-lg font-semibold">
                                        Comunicados recentes
                                    </h2>
                                </div>
                                <CardDescription>
                                    Novidades publicadas pela administração.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {overview.announcements.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Nenhum comunicado publicado.
                                    </p>
                                ) : (
                                    <ul className="divide-y">
                                        {overview.announcements.map((item) => (
                                            <li
                                                key={item.id}
                                                className="py-3 first:pt-0"
                                            >
                                                <Link
                                                    href={announcementShow(
                                                        item.id,
                                                    )}
                                                    className="font-medium hover:underline"
                                                >
                                                    {item.title}
                                                </Link>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {formatAnnouncementDate(
                                                        item.published_at,
                                                        timezone,
                                                    )}
                                                </p>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={announcementsIndex()}>
                                        Ver todos
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                        <Card className="border-cosphere-line/80 shadow-sm dark:border-border">
                            <CardHeader>
                                <div className="flex items-center gap-2">
                                    <Vote
                                        className="size-5 text-primary"
                                        aria-hidden="true"
                                    />
                                    <h2 className="text-lg font-semibold">
                                        Enquetes disponíveis
                                    </h2>
                                </div>
                                <CardDescription>
                                    Participe das decisões do condomínio.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {overview.polls.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Nenhuma enquete disponível no momento.
                                    </p>
                                ) : (
                                    <ul className="divide-y">
                                        {overview.polls.map((poll) => (
                                            <li
                                                key={poll.id}
                                                className="flex flex-wrap items-start justify-between gap-2 py-3 first:pt-0"
                                            >
                                                <div className="min-w-0">
                                                    <Link
                                                        href={pollShow(poll.id)}
                                                        className="font-medium break-words hover:underline"
                                                    >
                                                        {poll.question}
                                                    </Link>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {poll.ends_at
                                                            ? `Até ${formatAnnouncementDate(poll.ends_at, timezone)}`
                                                            : 'Sem data de encerramento'}
                                                    </p>
                                                </div>
                                                <Badge variant="secondary">
                                                    {poll.has_voted
                                                        ? 'Votou'
                                                        : 'Pendente'}
                                                </Badge>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={pollsIndex()}>Ver todas</Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </main>
            </div>
        </>
    );
}

MoradorDashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
