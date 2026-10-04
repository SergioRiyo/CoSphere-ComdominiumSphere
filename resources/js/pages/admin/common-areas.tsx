import { Head, Link, useHttp } from '@inertiajs/react';
import { CalendarDays, Pencil, Plus } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import CommonAreaFormDialog, {
    commonAreaStatusLabels,
} from '@/components/admin/common-area-form-dialog';
import CommonAreaAvailabilityDetails from '@/components/common-area-availability-details';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { availability, index } from '@/routes/admin/common-areas';
import type {
    CommonArea,
    CommonAreaAvailability,
    PaginatedCommonAreas,
} from '@/types/common-area';

function operatingHours(area: CommonArea): string {
    return area.available_from && area.available_until
        ? `${area.available_from.slice(0, 5)} – ${area.available_until.slice(0, 5)}`
        : 'Não definido';
}

export default function CommonAreasPage({
    areas,
    today,
}: {
    areas: PaginatedCommonAreas;
    today: string;
}) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingArea, setEditingArea] = useState<CommonArea | null>(null);
    const [availabilityArea, setAvailabilityArea] = useState<CommonArea | null>(
        null,
    );
    const [availabilityDate, setAvailabilityDate] = useState(today);
    const [availabilityResult, setAvailabilityResult] =
        useState<CommonAreaAvailability | null>(null);
    const [availabilityLoading, setAvailabilityLoading] = useState(false);
    const requestNumber = useRef(0);
    const http = useHttp<Record<string, never>, CommonAreaAvailability>({});

    const openForm = (area: CommonArea | null) => {
        setEditingArea(area);
        setDialogOpen(true);
    };

    async function consult(area: CommonArea, date: string) {
        const currentRequest = ++requestNumber.current;
        http.cancel();
        setAvailabilityArea(area);
        setAvailabilityResult(null);
        setAvailabilityLoading(Boolean(date));

        if (!date) {
            return;
        }

        try {
            const response = await http.get(
                availability.url(area.id, { query: { date } }),
            );

            if (currentRequest === requestNumber.current) {
                setAvailabilityResult(response);
            }
        } catch {
            if (currentRequest === requestNumber.current) {
                toast.error(
                    'Não foi possível consultar a disponibilidade. Tente novamente.',
                );
            }
        } finally {
            if (currentRequest === requestNumber.current) {
                setAvailabilityLoading(false);
            }
        }
    }

    return (
        <>
            <Head title="Áreas comuns" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Áreas comuns
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Gerencie horários, regras de utilização e tipo de
                            aprovação.
                        </p>
                    </div>
                    <Button onClick={() => openForm(null)}>
                        <Plus />
                        Nova área comum
                    </Button>
                </div>

                <Card className="gap-0 py-0">
                    {areas.data.length === 0 ? (
                        <div className="flex flex-col items-center gap-4 px-6 py-12 text-center">
                            <p className="font-medium">
                                Nenhuma área comum cadastrada.
                            </p>
                            <Button onClick={() => openForm(null)}>
                                <Plus />
                                Cadastrar área
                            </Button>
                        </div>
                    ) : (
                        <>
                            <div className="divide-y md:hidden">
                                {areas.data.map((area) => (
                                    <article
                                        key={area.id}
                                        className="flex flex-col gap-4 p-5"
                                    >
                                        <div className="flex flex-wrap items-start justify-between gap-2">
                                            <h2 className="min-w-0 font-medium wrap-anywhere">
                                                {area.name}
                                            </h2>
                                            <Badge
                                                variant={
                                                    area.status === 'active'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {
                                                    commonAreaStatusLabels[
                                                        area.status
                                                    ]
                                                }
                                            </Badge>
                                        </div>
                                        <dl className="grid gap-2 text-sm">
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Horário
                                                </dt>
                                                <dd>{operatingHours(area)}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Duração máxima
                                                </dt>
                                                <dd>
                                                    {
                                                        area.max_reservation_minutes
                                                    }{' '}
                                                    minutos
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Tipo de aprovação
                                                </dt>
                                                <dd>
                                                    {area.requires_approval
                                                        ? 'Aprovação manual'
                                                        : 'Aprovação automática'}
                                                </dd>
                                            </div>
                                        </dl>
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            <Button
                                                variant="outline"
                                                disabled={
                                                    area.status !== 'active'
                                                }
                                                onClick={() =>
                                                    void consult(
                                                        area,
                                                        availabilityDate,
                                                    )
                                                }
                                            >
                                                <CalendarDays />
                                                Disponibilidade
                                            </Button>
                                            <Button
                                                variant="outline"
                                                onClick={() => openForm(area)}
                                                aria-label={`Editar ${area.name}`}
                                            >
                                                <Pencil />
                                                Editar
                                            </Button>
                                        </div>
                                    </article>
                                ))}
                            </div>
                            <div className="hidden overflow-x-auto md:block">
                                <table className="w-full text-sm">
                                    <thead className="border-b bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-5 py-3">Nome</th>
                                            <th className="px-5 py-3">
                                                Horário
                                            </th>
                                            <th className="px-5 py-3">
                                                Duração máxima
                                            </th>
                                            <th className="px-5 py-3">
                                                Aprovação
                                            </th>
                                            <th className="px-5 py-3">
                                                Status
                                            </th>
                                            <th className="px-5 py-3 text-right">
                                                Ações
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {areas.data.map((area) => (
                                            <tr
                                                key={area.id}
                                                className="transition-colors hover:bg-muted/30"
                                            >
                                                <td className="max-w-xs px-5 py-4 font-medium wrap-anywhere">
                                                    {area.name}
                                                </td>
                                                <td className="px-5 py-4 whitespace-nowrap">
                                                    {operatingHours(area)}
                                                </td>
                                                <td className="px-5 py-4">
                                                    {
                                                        area.max_reservation_minutes
                                                    }{' '}
                                                    minutos
                                                </td>
                                                <td className="px-5 py-4">
                                                    {area.requires_approval
                                                        ? 'Aprovação manual'
                                                        : 'Aprovação automática'}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <Badge
                                                        variant={
                                                            area.status ===
                                                            'active'
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {
                                                            commonAreaStatusLabels[
                                                                area.status
                                                            ]
                                                        }
                                                    </Badge>
                                                </td>
                                                <td className="px-5 py-4 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            disabled={
                                                                area.status !==
                                                                'active'
                                                            }
                                                            onClick={() =>
                                                                void consult(
                                                                    area,
                                                                    availabilityDate,
                                                                )
                                                            }
                                                            aria-label={`Consultar disponibilidade de ${area.name}`}
                                                        >
                                                            <CalendarDays />
                                                            Consultar
                                                        </Button>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                openForm(area)
                                                            }
                                                            aria-label={`Editar ${area.name}`}
                                                        >
                                                            <Pencil />
                                                            Editar
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                    <div className="flex flex-col gap-3 border-t px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-sm text-muted-foreground">
                            {areas.total === 0
                                ? 'Nenhum resultado'
                                : `Mostrando ${areas.from}–${areas.to} de ${areas.total}`}
                        </p>
                        {areas.last_page > 1 && (
                            <nav
                                className="flex flex-wrap items-center gap-2"
                                aria-label="Paginação de áreas comuns"
                            >
                                {areas.prev_page_url ? (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={areas.prev_page_url}
                                            preserveScroll
                                        >
                                            Anterior
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled
                                    >
                                        Anterior
                                    </Button>
                                )}
                                <span className="text-sm text-muted-foreground">
                                    {areas.current_page} de {areas.last_page}
                                </span>
                                {areas.next_page_url ? (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={areas.next_page_url}
                                            preserveScroll
                                        >
                                            Próxima
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled
                                    >
                                        Próxima
                                    </Button>
                                )}
                            </nav>
                        )}
                    </div>
                </Card>
                {availabilityArea && (
                    <section className="grid gap-4" aria-live="polite">
                        <div className="grid gap-2 sm:max-w-xs">
                            <Label htmlFor="admin-availability-date">
                                Data para {availabilityArea.name}
                            </Label>
                            <Input
                                id="admin-availability-date"
                                type="date"
                                value={availabilityDate}
                                onChange={(event) => {
                                    setAvailabilityDate(event.target.value);
                                    void consult(
                                        availabilityArea,
                                        event.target.value,
                                    );
                                }}
                            />
                        </div>
                        {availabilityLoading ? (
                            <div className="grid gap-3" role="status">
                                <span>Consultando disponibilidade…</span>
                                <Skeleton className="h-40 w-full" />
                            </div>
                        ) : availabilityResult ? (
                            <CommonAreaAvailabilityDetails
                                result={availabilityResult}
                            />
                        ) : null}
                    </section>
                )}
            </div>
            <CommonAreaFormDialog
                key={`${editingArea?.id ?? 'create'}-${dialogOpen ? 'open' : 'closed'}`}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                area={editingArea}
            />
        </>
    );
}

CommonAreasPage.layout = {
    breadcrumbs: [{ title: 'Áreas comuns', href: index() }],
};
