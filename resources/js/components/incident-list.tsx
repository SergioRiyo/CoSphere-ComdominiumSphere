import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { IncidentSelect, incidentDate } from '@/components/incident-fields';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    index as adminIndex,
    show as adminShow,
} from '@/routes/admin/incidents';
import {
    create,
    index as residentIndex,
    show as residentShow,
} from '@/routes/morador/incidents';
import type { IncidentListProps, IncidentOption } from '@/types/incident';

export default function IncidentList({
    incidents,
    filters,
    options,
    can_create = false,
    admin = false,
}: IncidentListProps & { admin?: boolean }) {
    const [busy, setBusy] = useState(false);
    const index = admin ? adminIndex : residentIndex;
    const show = admin ? adminShow : residentShow;
    const form = useForm({
        type: filters.type || '',
        category: filters.category || '',
        status: filters.status || '',
        priority: filters.priority || '',
        resident_id: filters.resident_id || '',
        unit_id: filters.unit_id || '',
        date_from: filters.date_from || '',
        date_to: filters.date_to || '',
    });
    type SelectField =
        | 'type'
        | 'category'
        | 'status'
        | 'priority'
        | 'resident_id'
        | 'unit_id';
    const fields: {
        name: SelectField;
        label: string;
        options: IncidentOption[];
    }[] = [
        { name: 'type', label: 'Tipo', options: options.types },
        { name: 'category', label: 'Categoria', options: options.categories },
        { name: 'status', label: 'Status', options: options.statuses },
        ...(admin
            ? [
                  {
                      name: 'priority' as const,
                      label: 'Prioridade',
                      options: options.priorities,
                  },
                  {
                      name: 'resident_id' as const,
                      label: 'Morador',
                      options: options.residents || [],
                  },
                  {
                      name: 'unit_id' as const,
                      label: 'Unidade',
                      options: options.units || [],
                  },
              ]
            : []),
    ];
    const visiting = {
        preserveScroll: true,
        onStart: () => setBusy(true),
        onFinish: () => setBusy(false),
    };
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (busy) {
            return;
        }

        form.get(index.url(), visiting);
    }

    return (
        <>
            <Head title={admin ? 'Ocorrências' : 'Minhas solicitações'} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="grid gap-2">
                        <h1 className="text-2xl font-semibold">
                            {admin ? 'Ocorrências' : 'Minhas solicitações'}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Acompanhe solicitações, anexos e histórico de
                            atendimento.
                        </p>
                    </div>
                    {!admin && can_create && (
                        <Button asChild>
                            <Link href={create()}>Nova solicitação</Link>
                        </Button>
                    )}
                </header>
                {!admin && !can_create && (
                    <p role="status" className="rounded-xl border p-4 text-sm">
                        É necessário possuir uma unidade ativa para registrar
                        uma solicitação.
                    </p>
                )}
                <form
                    onSubmit={submit}
                    className="grid gap-4 rounded-xl border p-4"
                    aria-label="Filtros de solicitações"
                >
                    <fieldset
                        disabled={busy}
                        className="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-4"
                    >
                        {fields.map((field) => (
                            <IncidentSelect
                                key={field.name}
                                id={'filter-' + field.name}
                                label={field.label}
                                value={form.data[field.name]}
                                options={field.options}
                                onChange={(value) =>
                                    form.setData(field.name, value)
                                }
                                error={form.errors[field.name]}
                                all
                                disabled={busy}
                            />
                        ))}
                        <div className="grid gap-2">
                            <Label htmlFor="incident-from">Data inicial</Label>
                            <Input
                                id="incident-from"
                                type="date"
                                value={form.data.date_from}
                                onChange={(event) =>
                                    form.setData(
                                        'date_from',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.date_from} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="incident-to">Data final</Label>
                            <Input
                                id="incident-to"
                                type="date"
                                value={form.data.date_to}
                                onChange={(event) =>
                                    form.setData('date_to', event.target.value)
                                }
                            />
                            <InputError message={form.errors.date_to} />
                        </div>
                    </fieldset>
                    <p className="text-sm text-muted-foreground">
                        Período pela data de cadastro, incluindo o dia inicial e
                        o final.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Button disabled={busy} type="submit">
                            {busy ? 'Buscando...' : 'Aplicar filtros'}
                        </Button>
                        <Button
                            variant="outline"
                            disabled={busy}
                            type="button"
                            onClick={() =>
                                router.get(index.url(), {}, visiting)
                            }
                        >
                            Limpar filtros
                        </Button>
                    </div>
                </form>
                <p className="text-sm text-muted-foreground" aria-live="polite">
                    {incidents.total} solicitação(ões) encontrada(s)
                </p>
                {incidents.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        {Object.values(filters).some(Boolean)
                            ? 'Nenhuma solicitação encontrada para os filtros selecionados.'
                            : 'Nenhuma solicitação encontrada.'}
                    </div>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {incidents.data.map((incident) => (
                            <Card key={incident.id} className="min-w-0">
                                <CardHeader className="gap-3">
                                    <div className="flex flex-wrap gap-2">
                                        <Badge variant="secondary">
                                            {incident.status_label}
                                        </Badge>
                                        <Badge
                                            variant={
                                                incident.priority === 'high'
                                                    ? 'destructive'
                                                    : 'outline'
                                            }
                                        >
                                            Prioridade{' '}
                                            {incident.priority_label.toLowerCase()}
                                        </Badge>
                                    </div>
                                    <CardTitle className="break-words">
                                        <Link
                                            href={show(incident.id)}
                                            className="hover:underline"
                                        >
                                            {incident.title}
                                        </Link>
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-3 text-sm">
                                    <p>
                                        {incident.type_label} ·{' '}
                                        {incident.category_label}
                                    </p>
                                    {admin && (
                                        <p className="break-words">
                                            {incident.resident} ·{' '}
                                            {incident.unit}
                                        </p>
                                    )}
                                    <p className="text-muted-foreground">
                                        Cadastrada em{' '}
                                        {incidentDate(incident.created_at)}
                                    </p>
                                    <p>
                                        {incident.attachments_count} anexo(s) ·{' '}
                                        {incident.maintenance_count}{' '}
                                        manutenção(ões)
                                    </p>
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="w-full sm:w-fit"
                                    >
                                        <Link href={show(incident.id)}>
                                            Ver detalhes
                                        </Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
                <nav
                    aria-label="Paginação das solicitações"
                    className="flex flex-wrap items-center justify-between gap-3"
                >
                    <Button
                        variant="outline"
                        disabled={busy || incidents.current_page <= 1}
                        onClick={() =>
                            router.get(
                                index.url({
                                    query: {
                                        ...filters,
                                        page: incidents.current_page - 1,
                                    },
                                }),
                                {},
                                visiting,
                            )
                        }
                    >
                        Anterior
                    </Button>
                    <span className="text-sm">
                        Página {incidents.current_page} de {incidents.last_page}
                    </span>
                    <Button
                        variant="outline"
                        disabled={
                            busy ||
                            incidents.current_page >= incidents.last_page
                        }
                        onClick={() =>
                            router.get(
                                index.url({
                                    query: {
                                        ...filters,
                                        page: incidents.current_page + 1,
                                    },
                                }),
                                {},
                                visiting,
                            )
                        }
                    >
                        Próxima
                    </Button>
                </nav>
            </div>
        </>
    );
}
