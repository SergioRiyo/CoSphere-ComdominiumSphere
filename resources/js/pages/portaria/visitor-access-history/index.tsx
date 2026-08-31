import { Head, Link, router } from '@inertiajs/react';
import {
    CarFront,
    Clock3,
    DoorOpen,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    UserRoundSearch,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
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
import { VisitorAccessStatusBadge } from '@/components/visitor-access-status-badge';
import { dashboard } from '@/routes/portaria';
import { index } from '@/routes/portaria/visitor-access-history';
import type {
    PaginatedPortariaVisitorAccessHistory,
    PortariaVisitorAccessHistory,
    PortariaVisitorAccessHistoryFilters,
    PortariaVisitorAccessHistoryUnitOption,
    PortariaVisitorAccessSituation,
    PortariaVisitorAccessSituationOption,
} from '@/types';

type VisitorAccessHistoryPageProps = {
    accesses: PaginatedPortariaVisitorAccessHistory;
    filters: PortariaVisitorAccessHistoryFilters;
    unitOptions: PortariaVisitorAccessHistoryUnitOption[];
    situationOptions: PortariaVisitorAccessSituationOption[];
    timezone: string;
    errors?: Partial<Record<keyof PortariaVisitorAccessHistoryFilters, string>>;
};

export default function VisitorAccessHistoryPage({
    accesses,
    filters,
    unitOptions,
    situationOptions,
    timezone,
    errors = {},
}: VisitorAccessHistoryPageProps) {
    const [draftFilters, setDraftFilters] = useState(filters);
    const [isLoading, setIsLoading] = useState(false);
    const hasFilters = Boolean(
        filters.search ||
        filters.unit_id ||
        filters.situation ||
        filters.date_from ||
        filters.date_to,
    );

    const visitWithFilters = (
        nextFilters: PortariaVisitorAccessHistoryFilters,
    ) => {
        router.get(
            index.url(),
            {
                search: nextFilters.search || undefined,
                unit_id: nextFilters.unit_id ?? undefined,
                situation: nextFilters.situation || undefined,
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
        const emptyFilters: PortariaVisitorAccessHistoryFilters = {
            search: '',
            unit_id: null,
            situation: '',
            date_from: '',
            date_to: '',
        };

        setDraftFilters(emptyFilters);
        visitWithFilters(emptyFilters);
    };

    return (
        <>
            <Head title="Histórico de acessos" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex max-w-3xl flex-col gap-2">
                    <div className="flex items-center gap-2 text-sm font-medium text-primary">
                        <ShieldCheck className="size-4" aria-hidden="true" />
                        Operação da portaria
                    </div>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Histórico de acessos
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Consulte entradas, saídas e recusas registradas pela
                        portaria.
                    </p>
                </header>

                <Card className="border-primary/15 bg-gradient-to-br from-card to-muted/30">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <SlidersHorizontal className="size-4" />
                            Pesquisa e filtros
                        </CardTitle>
                        <CardDescription>
                            Combine visitante, unidade, situação e período.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submitFilters}
                            className="grid gap-4 lg:grid-cols-2 xl:grid-cols-[minmax(14rem,1.4fr)_12rem_11rem_10rem_10rem_auto] xl:items-end"
                        >
                            <div className="grid gap-2 lg:col-span-2 xl:col-span-1">
                                <Label htmlFor="access-history-search">
                                    Visitante
                                </Label>
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        id="access-history-search"
                                        value={draftFilters.search}
                                        onChange={(event) =>
                                            setDraftFilters({
                                                ...draftFilters,
                                                search: event.target.value,
                                            })
                                        }
                                        className="pl-9"
                                        placeholder="Nome do visitante"
                                    />
                                </div>
                                <InputError message={errors.search} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="access-history-unit">
                                    Unidade
                                </Label>
                                <Select
                                    value={
                                        draftFilters.unit_id === null
                                            ? 'all'
                                            : String(draftFilters.unit_id)
                                    }
                                    onValueChange={(unitId) =>
                                        setDraftFilters({
                                            ...draftFilters,
                                            unit_id:
                                                unitId === 'all'
                                                    ? null
                                                    : Number(unitId),
                                        })
                                    }
                                >
                                    <SelectTrigger
                                        id="access-history-unit"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Todas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Todas as unidades
                                        </SelectItem>
                                        {unitOptions.map((unit) => (
                                            <SelectItem
                                                key={unit.id}
                                                value={String(unit.id)}
                                            >
                                                {unit.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.unit_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="access-history-situation">
                                    Situação
                                </Label>
                                <Select
                                    value={draftFilters.situation || 'all'}
                                    onValueChange={(situation) =>
                                        setDraftFilters({
                                            ...draftFilters,
                                            situation:
                                                situation === 'all'
                                                    ? ''
                                                    : (situation as PortariaVisitorAccessSituation),
                                        })
                                    }
                                >
                                    <SelectTrigger
                                        id="access-history-situation"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Todas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Todas as situações
                                        </SelectItem>
                                        {situationOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.situation} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="access-history-date-from">
                                    A partir de
                                </Label>
                                <Input
                                    id="access-history-date-from"
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
                                <Label htmlFor="access-history-date-to">
                                    Até
                                </Label>
                                <Input
                                    id="access-history-date-to"
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
                        <HistorySkeleton />
                    ) : accesses.data.length === 0 ? (
                        <EmptyState hasFilters={hasFilters} />
                    ) : (
                        <>
                            <DesktopTable
                                accesses={accesses.data}
                                timezone={timezone}
                            />
                            <MobileCards
                                accesses={accesses.data}
                                timezone={timezone}
                            />
                        </>
                    )}

                    <div className="flex flex-col gap-3 border-t px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-sm text-muted-foreground">
                            {accesses.total === 0
                                ? 'Nenhum resultado'
                                : accesses.from === null
                                  ? 'Página sem resultados'
                                  : `Mostrando ${accesses.from}–${accesses.to} de ${accesses.total}`}
                        </p>

                        {accesses.last_page > 1 && (
                            <nav
                                className="flex flex-wrap items-center gap-1"
                                aria-label="Paginação do histórico de acessos"
                            >
                                <PaginationLink
                                    href={accesses.prev_page_url}
                                    label="Anterior"
                                    setLoading={setIsLoading}
                                />
                                {accesses.links
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
                                    href={accesses.next_page_url}
                                    label="Próxima"
                                    setLoading={setIsLoading}
                                />
                            </nav>
                        )}
                    </div>
                </Card>
            </div>
        </>
    );
}

function DesktopTable({
    accesses,
    timezone,
}: {
    accesses: PortariaVisitorAccessHistory[];
    timezone: string;
}) {
    return (
        <div className="hidden overflow-x-auto lg:block">
            <table className="w-full min-w-[1040px] text-sm">
                <thead className="border-b bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    <tr>
                        <th className="px-5 py-3">Visitante</th>
                        <th className="px-5 py-3">Unidade</th>
                        <th className="px-5 py-3">Entrada</th>
                        <th className="px-5 py-3">Saída</th>
                        <th className="px-5 py-3">Porteiros</th>
                        <th className="px-5 py-3">Situação</th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {accesses.map((access, position) => (
                        <tr
                            key={accessKey(access, position)}
                            className="transition-colors hover:bg-muted/30"
                        >
                            <td className="px-5 py-4">
                                <VisitorIdentity access={access} />
                            </td>
                            <td className="px-5 py-4 text-muted-foreground">
                                {unitLabel(access)}
                            </td>
                            <td className="px-5 py-4">
                                {formatDateTime(access.entry_time, timezone)}
                            </td>
                            <td className="px-5 py-4">
                                {formatDateTime(access.exit_time, timezone)}
                            </td>
                            <td className="px-5 py-4">
                                <Doormen access={access} />
                            </td>
                            <td className="px-5 py-4">
                                <SituationBadge access={access} />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function MobileCards({
    accesses,
    timezone,
}: {
    accesses: PortariaVisitorAccessHistory[];
    timezone: string;
}) {
    return (
        <div className="grid gap-3 p-3 lg:hidden">
            {accesses.map((access, position) => (
                <article
                    key={accessKey(access, position)}
                    className="grid gap-4 rounded-xl border bg-card p-4 shadow-sm"
                >
                    <div className="flex items-start justify-between gap-3">
                        <VisitorIdentity access={access} />
                        <SituationBadge access={access} />
                    </div>
                    <dl className="grid gap-3 border-y py-3 text-sm">
                        <AccessDetail
                            icon={DoorOpen}
                            label="Unidade"
                            value={unitLabel(access)}
                        />
                        <AccessDetail
                            icon={Clock3}
                            label="Entrada"
                            value={formatDateTime(access.entry_time, timezone)}
                        />
                        <AccessDetail
                            icon={Clock3}
                            label="Saída"
                            value={formatDateTime(access.exit_time, timezone)}
                        />
                        <AccessDetail
                            icon={ShieldCheck}
                            label="Porteiro da entrada"
                            value={
                                access.entry_doorman_name ?? 'Não identificado'
                            }
                        />
                        <AccessDetail
                            icon={ShieldCheck}
                            label="Porteiro da saída"
                            value={access.exit_doorman_name ?? 'Não informado'}
                        />
                    </dl>
                </article>
            ))}
        </div>
    );
}

function VisitorIdentity({ access }: { access: PortariaVisitorAccessHistory }) {
    return (
        <div className="min-w-0">
            <p className="font-medium break-words">{access.visitor_name}</p>
            <p className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                <CarFront className="size-3" aria-hidden="true" />
                {access.vehicle_plate ?? 'Placa não informada'}
            </p>
        </div>
    );
}

function Doormen({ access }: { access: PortariaVisitorAccessHistory }) {
    return (
        <div className="grid gap-1 text-xs text-muted-foreground">
            <p>
                <span className="font-medium text-foreground">Entrada:</span>{' '}
                {access.entry_doorman_name ?? 'Não identificado'}
            </p>
            <p>
                <span className="font-medium text-foreground">Saída:</span>{' '}
                {access.exit_doorman_name ?? 'Não informado'}
            </p>
        </div>
    );
}

function AccessDetail({
    icon: Icon,
    label,
    value,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
}) {
    return (
        <div className="flex min-w-0 items-start gap-3">
            <Icon
                className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            <div className="min-w-0">
                <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </dt>
                <dd className="mt-0.5 break-words">{value}</dd>
            </div>
        </div>
    );
}

function SituationBadge({ access }: { access: PortariaVisitorAccessHistory }) {
    return (
        <VisitorAccessStatusBadge
            situation={access.situation}
            label={access.situation_label}
        />
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
                        ? 'Nenhum acesso encontrado'
                        : 'Nenhum acesso registrado'}
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                    {hasFilters
                        ? 'Tente ajustar os filtros da pesquisa.'
                        : 'Entradas, saídas e recusas registradas aparecerão aqui.'}
                </p>
            </div>
        </div>
    );
}

function HistorySkeleton() {
    return (
        <div className="grid gap-4 p-5" aria-label="Carregando histórico">
            {Array.from({ length: 5 }).map((_, position) => (
                <div
                    key={position}
                    className="grid gap-3 border-b pb-4 lg:grid-cols-6"
                >
                    <Skeleton className="h-5 w-40" />
                    <Skeleton className="h-5 w-28" />
                    <Skeleton className="h-5 w-28" />
                    <Skeleton className="h-5 w-28" />
                    <Skeleton className="h-5 w-32" />
                    <Skeleton className="h-5 w-20" />
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

function unitLabel(access: PortariaVisitorAccessHistory) {
    return access.unit.block
        ? `Bloco ${access.unit.block} · Unidade ${access.unit.number}`
        : `Unidade ${access.unit.number}`;
}

function formatDateTime(value: string | null, timezone: string) {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(value));
}

function accessKey(access: PortariaVisitorAccessHistory, position: number) {
    return `${access.visitor_name}-${access.entry_time ?? access.exit_time ?? 'sem-horario'}-${position}`;
}

VisitorAccessHistoryPage.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Histórico de acessos',
            href: index(),
        },
    ],
};
