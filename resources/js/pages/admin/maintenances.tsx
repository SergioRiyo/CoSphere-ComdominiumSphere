import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { IncidentSelect, incidentDate } from '@/components/incident-fields';
import InputError from '@/components/input-error';
import { maintenanceMoney } from '@/components/maintenance-history';
import ManagementPagination from '@/components/management-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index, show } from '@/routes/admin/maintenances';
import type {
    Maintenance,
    MaintenanceOptions,
    Paged,
} from '@/types/maintenance';
export default function Maintenances({
    maintenances,
    filters,
    options,
}: {
    maintenances: Paged<Maintenance>;
    filters: Record<string, string>;
    options: MaintenanceOptions;
}) {
    const form = useForm({
        status: filters.status || '',
        unit_id: filters.unit_id || '',
        admin_id: filters.admin_id || '',
        service_provider_id: filters.service_provider_id || '',
        link: filters.link || '',
        date_from: filters.date_from || '',
        date_to: filters.date_to || '',
    });
    const [busy, setBusy] = useState(false);
    const fields = [
        { key: 'status', label: 'Status', options: options.statuses },
        { key: 'unit_id', label: 'Unidade', options: options.units },
        { key: 'admin_id', label: 'Responsável', options: options.admins },
        {
            key: 'service_provider_id',
            label: 'Prestador',
            options: options.providers,
        },
        {
            key: 'link',
            label: 'Origem',
            options: [
                { value: 'linked', label: 'Vinculada a ocorrência' },
                { value: 'direct', label: 'Independente' },
            ],
        },
    ] as const;

    return (
        <>
            <Head title="Manutenções" />
            <div className="grid gap-6 p-4 sm:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold">Manutenções</h1>
                    <Button asChild>
                        <Link href={create()}>Nova manutenção</Link>
                    </Button>
                </header>
                <form
                    className="grid gap-4 rounded-xl border p-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.get(index.url(), { preserveScroll: true });
                    }}
                >
                    <fieldset
                        disabled={form.processing}
                        className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                    >
                        {fields.map((field) => (
                            <IncidentSelect
                                key={field.key}
                                id={field.key}
                                label={field.label}
                                value={form.data[field.key]}
                                options={[...field.options]}
                                all
                                onChange={(value) =>
                                    form.setData(field.key, value)
                                }
                                error={form.errors[field.key]}
                            />
                        ))}
                        {(['date_from', 'date_to'] as const).map((key) => (
                            <div key={key} className="grid gap-2">
                                <Label htmlFor={key}>
                                    {key === 'date_from'
                                        ? 'Data inicial'
                                        : 'Data final'}
                                </Label>
                                <Input
                                    id={key}
                                    type="date"
                                    value={form.data[key]}
                                    onChange={(event) =>
                                        form.setData(key, event.target.value)
                                    }
                                />
                                <InputError message={form.errors[key]} />
                            </div>
                        ))}
                    </fieldset>
                    <p className="text-sm text-muted-foreground">
                        Período pela data de cadastro, incluindo o dia inicial e
                        o final.
                    </p>
                    <div className="flex flex-wrap gap-3">
                        <Button disabled={form.processing}>
                            Aplicar filtros
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => router.get(index.url())}
                        >
                            Limpar filtros
                        </Button>
                    </div>
                </form>
                <p className="text-sm text-muted-foreground">
                    {maintenances.total} manutenção(ões)
                </p>
                {maintenances.data.length === 0 && (
                    <p>
                        Nenhuma manutenção encontrada para os filtros
                        selecionados.
                    </p>
                )}
                <div className="grid gap-4 lg:grid-cols-2">
                    {maintenances.data.map((item) => (
                        <article
                            key={item.id}
                            className="grid min-w-0 gap-3 rounded-xl border p-5 break-words"
                        >
                            <div className="flex flex-wrap justify-between gap-2">
                                <h2 className="font-semibold">
                                    Manutenção #{item.id} · {item.unit}
                                </h2>
                                <Badge variant="secondary">
                                    {item.status_label}
                                </Badge>
                            </div>
                            <p>{item.description}</p>
                            <p className="text-sm">
                                {item.incident_title
                                    ? 'Ocorrência: ' + item.incident_title
                                    : 'Manutenção independente'}
                            </p>
                            <p className="text-sm">
                                Responsável: {item.admin || 'Não atribuído'} ·
                                Prestador: {item.provider || 'Não atribuído'}
                                {item.provider_archived && ' (arquivado)'}
                            </p>
                            <p className="text-sm">
                                Agendamento: {incidentDate(item.scheduled_at)}
                            </p>
                            <p className="text-sm">
                                Execução: {incidentDate(item.executed_at)} ·
                                Custo: {maintenanceMoney(item.cost)}
                            </p>
                            <Button asChild variant="outline" className="w-fit">
                                <Link href={show(item.id)}>
                                    Gerenciar manutenção
                                </Link>
                            </Button>
                        </article>
                    ))}
                </div>
                <ManagementPagination
                    page={maintenances.current_page}
                    last={maintenances.last_page}
                    busy={busy || form.processing}
                    onChange={(page) =>
                        router.get(
                            index.url(),
                            { ...filters, page },
                            {
                                onStart: () => setBusy(true),
                                onFinish: () => setBusy(false),
                            },
                        )
                    }
                />
            </div>
        </>
    );
}
Maintenances.layout = {
    breadcrumbs: [{ title: 'Manutenções', href: index() }],
};
