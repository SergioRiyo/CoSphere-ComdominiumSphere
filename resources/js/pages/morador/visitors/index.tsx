import { Head, Link, router, usePoll } from '@inertiajs/react';
import {
    CalendarDays,
    Eye,
    Plus,
    Search,
    SlidersHorizontal,
    UserRoundSearch,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import VisitorAuthorizationFormDialog from '@/components/morador/visitor-authorization-form-dialog';
import { InvitationLinkDialog } from '@/components/morador/visitor-invitation-dialog';
import VisitorInvitationDialog from '@/components/morador/visitor-invitation-dialog';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { VisitorStatusBadge } from '@/components/visitor-status-badge';
import { index, show } from '@/routes/morador/visitors';
import type {
    PaginatedVisitorAuthorizations,
    VisitorAuthorizationFilters,
    VisitorAuthorizationStatusOption,
    VisitorAuthorizationSummary,
} from '@/types';

type VisitorsPageProps = {
    authorizations: PaginatedVisitorAuthorizations;
    filters: VisitorAuthorizationFilters;
    statusOptions: VisitorAuthorizationStatusOption[];
    timezone: string;
    invitationUrl: string | null;
    errors?: Partial<Record<keyof VisitorAuthorizationFilters, string>>;
};

export default function VisitorsPage({
    authorizations,
    filters,
    statusOptions,
    timezone,
    errors = {},
    invitationUrl,
}: VisitorsPageProps) {
    usePoll(60_000, { only: ['authorizations'] });

    const [draftFilters, setDraftFilters] = useState(filters);
    const [isLoading, setIsLoading] = useState(false);
    const [isAuthorizationDialogOpen, setIsAuthorizationDialogOpen] =
        useState(false);
    const [isInvitationDialogOpen, setIsInvitationDialogOpen] = useState(false);
    const hasFilters = Boolean(
        filters.search ||
        filters.status ||
        filters.date_from ||
        filters.date_to,
    );

    const visitWithFilters = (nextFilters: VisitorAuthorizationFilters) => {
        router.get(
            index.url(),
            {
                search: nextFilters.search || undefined,
                status: nextFilters.status || undefined,
                date_from: nextFilters.date_from || undefined,
                date_to: nextFilters.date_to || undefined,
            },
            {
                preserveScroll: true,
                replace: true,
                onStart: () => setIsLoading(true),
                onFinish: () => setIsLoading(false),
            },
        );
    };

    const submitFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        visitWithFilters(draftFilters);
    };

    const clearFilters = () => {
        const emptyFilters: VisitorAuthorizationFilters = {
            search: '',
            status: '',
            date_from: '',
            date_to: '',
        };

        setDraftFilters(emptyFilters);
        visitWithFilters(emptyFilters);
    };

    return (
        <>
            <Head title="Visitantes" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex max-w-3xl flex-col gap-2">
                        <div className="flex items-center gap-2 text-sm font-medium text-primary">
                            <UserRoundSearch className="size-4" />
                            Histórico da unidade
                        </div>
                        <h1 className="text-3xl font-semibold tracking-tight">
                            Visitantes
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Consulte convites e autorizações vinculados à sua
                            unidade.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setIsInvitationDialogOpen(true)}
                        >
                            <Plus />
                            Novo convite
                        </Button>
                        <Button
                            onClick={() => setIsAuthorizationDialogOpen(true)}
                        >
                            <Plus />
                            Nova autorização
                        </Button>
                    </div>
                </header>

                <Card className="border-primary/15 bg-gradient-to-br from-card to-muted/30">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <SlidersHorizontal className="size-4" />
                            Pesquisa e filtros
                        </CardTitle>
                        <CardDescription>
                            Busque por nome ou CPF e combine status e período.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submitFilters}
                            className="grid gap-4 lg:grid-cols-2 xl:grid-cols-[minmax(16rem,1.4fr)_12rem_10rem_10rem_auto] xl:items-end"
                        >
                            <div className="grid gap-2 lg:col-span-2 xl:col-span-1">
                                <Label htmlFor="visitor-search">
                                    Visitante
                                </Label>
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        id="visitor-search"
                                        value={draftFilters.search}
                                        onChange={(event) =>
                                            setDraftFilters({
                                                ...draftFilters,
                                                search: event.target.value,
                                            })
                                        }
                                        className="pl-9"
                                        placeholder="Nome ou CPF"
                                    />
                                </div>
                                <InputError message={errors.search} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="visitor-status">Status</Label>
                                <Select
                                    value={draftFilters.status || 'all'}
                                    onValueChange={(status) =>
                                        setDraftFilters({
                                            ...draftFilters,
                                            status:
                                                status === 'all'
                                                    ? ''
                                                    : (status as VisitorAuthorizationFilters['status']),
                                        })
                                    }
                                >
                                    <SelectTrigger
                                        id="visitor-status"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Todos" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Todos os status
                                        </SelectItem>
                                        {statusOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.status} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="date-from">A partir de</Label>
                                <Input
                                    id="date-from"
                                    type="date"
                                    value={draftFilters.date_from}
                                    max={draftFilters.date_to || undefined}
                                    onChange={(event) =>
                                        setDraftFilters({
                                            ...draftFilters,
                                            date_from: event.target.value,
                                        })
                                    }
                                />
                                <InputError message={errors.date_from} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="date-to">Até</Label>
                                <Input
                                    id="date-to"
                                    type="date"
                                    value={draftFilters.date_to}
                                    min={draftFilters.date_from || undefined}
                                    onChange={(event) =>
                                        setDraftFilters({
                                            ...draftFilters,
                                            date_to: event.target.value,
                                        })
                                    }
                                />
                                <InputError message={errors.date_to} />
                            </div>

                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    className="flex-1 xl:flex-none"
                                    disabled={isLoading}
                                >
                                    Filtrar
                                </Button>
                                {hasFilters && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        onClick={clearFilters}
                                        disabled={isLoading}
                                        aria-label="Limpar filtros"
                                    >
                                        <X />
                                    </Button>
                                )}
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card className="gap-0 overflow-hidden py-0">
                    {isLoading ? (
                        <VisitorListSkeleton />
                    ) : authorizations.data.length === 0 ? (
                        <EmptyState hasFilters={hasFilters} />
                    ) : (
                        <>
                            <DesktopTable
                                authorizations={authorizations.data}
                                timezone={timezone}
                            />
                            <MobileCards
                                authorizations={authorizations.data}
                                timezone={timezone}
                            />
                        </>
                    )}

                    <div className="flex flex-col gap-3 border-t px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-sm text-muted-foreground">
                            {authorizations.total === 0
                                ? 'Nenhum resultado'
                                : authorizations.from === null
                                  ? 'Página sem resultados'
                                  : `Mostrando ${authorizations.from}–${authorizations.to} de ${authorizations.total}`}
                        </p>

                        {authorizations.last_page > 1 && (
                            <nav
                                className="flex flex-wrap items-center gap-1"
                                aria-label="Paginação de visitantes"
                            >
                                <PaginationLink
                                    href={authorizations.prev_page_url}
                                    label="Anterior"
                                    setLoading={setIsLoading}
                                />
                                {authorizations.links
                                    .slice(1, -1)
                                    .map((link, position) => (
                                        <PaginationLink
                                            key={`${link.label}-${position}`}
                                            href={link.url}
                                            label={link.label}
                                            active={link.active}
                                            compact
                                            setLoading={setIsLoading}
                                        />
                                    ))}
                                <PaginationLink
                                    href={authorizations.next_page_url}
                                    label="Próxima"
                                    setLoading={setIsLoading}
                                />
                            </nav>
                        )}
                    </div>
                </Card>

                <VisitorAuthorizationFormDialog
                    open={isAuthorizationDialogOpen}
                    onOpenChange={setIsAuthorizationDialogOpen}
                    timezone={timezone}
                />
                <VisitorInvitationDialog
                    open={isInvitationDialogOpen}
                    onOpenChange={setIsInvitationDialogOpen}
                />
                <InvitationLinkDialog
                    url={invitationUrl}
                    onOpenChange={() =>
                        router.reload({ only: ['invitationUrl'] })
                    }
                />
            </div>
        </>
    );
}

function DesktopTable({
    authorizations,
    timezone,
}: {
    authorizations: VisitorAuthorizationSummary[];
    timezone: string;
}) {
    return (
        <div className="hidden overflow-x-auto md:block">
            <table className="w-full min-w-[780px] text-sm">
                <thead className="border-b bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    <tr>
                        <th className="px-5 py-3">Visitante</th>
                        <th className="px-5 py-3">Data e horário</th>
                        <th className="px-5 py-3">Unidade</th>
                        <th className="px-5 py-3">Status</th>
                        <th className="px-5 py-3 text-right">Ação</th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {authorizations.map((authorization) => (
                        <tr
                            key={authorization.id}
                            className="transition-colors hover:bg-muted/30"
                        >
                            <td className="px-5 py-4">
                                <VisitorIdentity
                                    authorization={authorization}
                                />
                            </td>
                            <td className="px-5 py-4">
                                <Period
                                    authorization={authorization}
                                    timezone={timezone}
                                />
                            </td>
                            <td className="px-5 py-4 text-muted-foreground">
                                {unitLabel(authorization)}
                            </td>
                            <td className="px-5 py-4">
                                <VisitorStatusBadge
                                    status={authorization.status}
                                    label={authorization.status_label}
                                />
                            </td>
                            <td className="px-5 py-4 text-right">
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={show(authorization.id)}
                                        prefetch
                                    >
                                        <Eye />
                                        Detalhes
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function MobileCards({
    authorizations,
    timezone,
}: {
    authorizations: VisitorAuthorizationSummary[];
    timezone: string;
}) {
    return (
        <div className="grid gap-3 p-3 md:hidden">
            {authorizations.map((authorization) => (
                <article
                    key={authorization.id}
                    className="grid gap-4 rounded-xl border bg-card p-4 shadow-sm"
                >
                    <div className="flex items-start justify-between gap-3">
                        <VisitorIdentity authorization={authorization} />
                        <VisitorStatusBadge
                            status={authorization.status}
                            label={authorization.status_label}
                        />
                    </div>
                    <div className="grid gap-3 border-y py-3 text-sm">
                        <Period
                            authorization={authorization}
                            timezone={timezone}
                        />
                        <p className="text-muted-foreground">
                            {unitLabel(authorization)}
                        </p>
                    </div>
                    <Button variant="outline" className="w-full" asChild>
                        <Link href={show(authorization.id)}>
                            <Eye />
                            Ver detalhes
                        </Link>
                    </Button>
                </article>
            ))}
        </div>
    );
}

function VisitorIdentity({
    authorization,
}: {
    authorization: VisitorAuthorizationSummary;
}) {
    return authorization.visitor ? (
        <div>
            <p className="font-medium">{authorization.visitor.name}</p>
            <p className="text-xs text-muted-foreground">
                {authorization.visitor.cpf}
            </p>
        </div>
    ) : (
        <div>
            <p className="font-medium">Cadastro pendente</p>
            <p className="text-xs text-muted-foreground">
                Dados ainda não preenchidos
            </p>
        </div>
    );
}

function Period({
    authorization,
    timezone,
}: {
    authorization: VisitorAuthorizationSummary;
    timezone: string;
}) {
    const start = new Date(authorization.start_date);
    const end = new Date(authorization.end_date);
    const startDate = formatDate(start, timezone);
    const endDate = formatDate(end, timezone);

    return (
        <div className="flex items-start gap-2">
            <CalendarDays className="mt-0.5 size-4 text-muted-foreground" />
            <div>
                <p className="font-medium">
                    {startDate === endDate
                        ? startDate
                        : `${startDate}–${endDate}`}
                </p>
                <p className="text-xs text-muted-foreground">
                    {formatTime(start, timezone)}–{formatTime(end, timezone)}
                </p>
            </div>
        </div>
    );
}

function EmptyState({ hasFilters }: { hasFilters: boolean }) {
    return (
        <div className="grid place-items-center gap-3 px-6 py-16 text-center">
            <div className="grid size-12 place-items-center rounded-full bg-muted">
                <UserRoundSearch className="size-6 text-muted-foreground" />
            </div>
            <div>
                <p className="font-medium">
                    {hasFilters
                        ? 'Nenhuma autorização encontrada'
                        : 'Nenhuma visita registrada'}
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                    {hasFilters
                        ? 'Tente ajustar os filtros da pesquisa.'
                        : 'As autorizações da sua unidade aparecerão aqui.'}
                </p>
            </div>
        </div>
    );
}

function VisitorListSkeleton() {
    return (
        <div className="grid gap-4 p-5" aria-label="Carregando visitantes">
            {Array.from({ length: 5 }).map((_, position) => (
                <div
                    key={position}
                    className="grid gap-3 border-b pb-4 md:grid-cols-4"
                >
                    <Skeleton className="h-5 w-40" />
                    <Skeleton className="h-5 w-32" />
                    <Skeleton className="h-5 w-28" />
                    <Skeleton className="h-5 w-20 md:justify-self-end" />
                </div>
            ))}
        </div>
    );
}

function PaginationLink({
    href,
    label,
    active = false,
    compact = false,
    setLoading,
}: {
    href: string | null;
    label: string;
    active?: boolean;
    compact?: boolean;
    setLoading: (loading: boolean) => void;
}) {
    if (href === null) {
        return (
            <Button
                type="button"
                variant="outline"
                size={compact ? 'icon' : 'sm'}
                disabled
            >
                {label}
            </Button>
        );
    }

    return (
        <Button
            variant={active ? 'default' : 'outline'}
            size={compact ? 'icon' : 'sm'}
            asChild
        >
            <Link
                href={href}
                preserveScroll
                onStart={() => setLoading(true)}
                onFinish={() => setLoading(false)}
            >
                {label}
            </Link>
        </Button>
    );
}

function unitLabel(authorization: VisitorAuthorizationSummary) {
    return authorization.unit.block
        ? `Bloco ${authorization.unit.block} · Unidade ${authorization.unit.number}`
        : `Unidade ${authorization.unit.number}`;
}

function formatDate(date: Date, timezone: string) {
    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeZone: timezone,
    }).format(date);
}

function formatTime(date: Date, timezone: string) {
    return new Intl.DateTimeFormat('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: timezone,
    }).format(date);
}

VisitorsPage.layout = {
    breadcrumbs: [
        {
            title: 'Visitantes',
            href: index(),
        },
    ],
};
