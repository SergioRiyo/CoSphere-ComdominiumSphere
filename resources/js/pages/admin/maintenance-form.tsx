import { Head, Link, useForm } from '@inertiajs/react';
import { useRef } from 'react';
import { toast } from 'sonner';
import { IncidentSelect, incidentDate } from '@/components/incident-fields';
import InputError from '@/components/input-error';
import MaintenanceHistory, {
    maintenanceMoney,
} from '@/components/maintenance-history';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show as incidentShow } from '@/routes/admin/incidents';
import { index, store, update } from '@/routes/admin/maintenances';
import type { Maintenance, MaintenanceOptions } from '@/types/maintenance';
export default function MaintenanceForm({
    maintenance,
    options,
    default_admin_id,
}: {
    maintenance: Maintenance | null;
    options: MaintenanceOptions;
    default_admin_id: number;
}) {
    const lock = useRef(false);
    const form = useForm({
        target_type: maintenance?.common_area_id ? 'common_area' : 'unit',
        unit_id: maintenance?.unit_id ? String(maintenance.unit_id) : '',
        common_area_id: maintenance?.common_area_id
            ? String(maintenance.common_area_id)
            : '',
        admin_id: String(maintenance?.admin_id || default_admin_id),
        service_provider_id: maintenance?.service_provider_id
            ? String(maintenance.service_provider_id)
            : 'none',
        description: maintenance?.description || '',
        scheduled_at: maintenance?.scheduled_at?.replace(' ', 'T') || '',
        cost: maintenance?.cost || '',
        status: '',
        reason: '',
    });
    const editable = !maintenance || maintenance.can_edit;
    const providers = [
        { value: 'none', label: 'Sem prestador' },
        ...options.providers,
    ];

    if (
        maintenance?.service_provider_id &&
        !providers.some(
            (option) =>
                option.value === String(maintenance.service_provider_id),
        )
    ) {
        providers.push({
            value: String(maintenance.service_provider_id),
            label:
                (maintenance.provider || 'Prestador') +
                ' (arquivado — vínculo atual)',
        });
    }

    const admins = [...options.admins];
    const commonAreas = [...options.common_areas];

    if (
        maintenance?.common_area_id &&
        !commonAreas.some(
            (option) => option.value === String(maintenance.common_area_id),
        )
    ) {
        commonAreas.push({
            value: String(maintenance.common_area_id),
            label: (maintenance.common_area || 'Área comum') + ' (indisponível)',
        });
    }

    if (
        maintenance?.admin_id &&
        !admins.some((option) => option.value === String(maintenance.admin_id))
    ) {
        admins.push({
            value: String(maintenance.admin_id),
            label: (maintenance.admin || 'Responsável') + ' (indisponível)',
        });
    }

    return (
        <>
            <Head
                title={
                    maintenance
                        ? 'Manutenção #' + maintenance.id
                        : 'Nova manutenção'
                }
            />
            <div className="grid gap-6 p-4 sm:p-6">
                <Button asChild variant="outline" className="w-fit">
                    <Link href={index()}>Voltar às manutenções</Link>
                </Button>
                <header className="grid gap-3">
                    <h1 className="text-2xl font-semibold">
                        {maintenance
                            ? 'Manutenção #' + maintenance.id
                            : 'Nova manutenção independente'}
                    </h1>
                    {maintenance && (
                        <>
                            <Badge variant="secondary" className="w-fit">
                                {maintenance.status_label}
                            </Badge>
                            <p>
                                {maintenance.common_area
                                    ? 'Área comum: ' + maintenance.common_area
                                    : 'Unidade: ' + maintenance.unit}
                            </p>
                            {maintenance.common_area_block_id && (
                                <p className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm">
                                    Bloqueio #{maintenance.common_area_block_id}{' '}
                                    vinculado: {incidentDate(maintenance.block_starts_at)}{' '}
                                    até {incidentDate(maintenance.block_ends_at)}.
                                </p>
                            )}
                            <p>
                                Execução:{' '}
                                {incidentDate(maintenance.executed_at)} · Custo:{' '}
                                {maintenanceMoney(maintenance.cost)}
                            </p>
                            {maintenance.incident_id && (
                                <Link
                                    className="underline"
                                    href={incidentShow(maintenance.incident_id)}
                                >
                                    Ocorrência: {maintenance.incident_title}
                                </Link>
                            )}
                        </>
                    )}
                </header>
                {!editable && (
                    <p className="rounded-xl border p-4">
                        Manutenção encerrada. Os dados e o histórico permanecem
                        disponíveis para consulta.
                    </p>
                )}
                <div className="grid min-w-0 gap-6 xl:grid-cols-2">
                    <form
                        className="grid min-w-0 content-start gap-5 rounded-xl border p-5"
                        onSubmit={(event) => {
                            event.preventDefault();

                            if (lock.current || !editable) {
                                return;
                            }

                            lock.current = true;
                            form.transform((data) => ({
                                description: data.description,
                                admin_id: data.admin_id,
                                service_provider_id:
                                    data.service_provider_id === 'none'
                                        ? null
                                        : data.service_provider_id,
                                scheduled_at: data.scheduled_at || null,
                                cost: data.cost.trim()
                                    ? data.cost.replace(',', '.')
                                    : null,
                                ...(!maintenance
                                    ? data.target_type === 'unit'
                                        ? { unit_id: data.unit_id }
                                        : {
                                              common_area_id:
                                                  data.common_area_id,
                                          }
                                    : maintenance.common_area_id
                                      ? {
                                            common_area_id:
                                                data.common_area_id,
                                        }
                                    : {}),
                                ...(data.status
                                    ? {
                                          status: data.status,
                                          reason: data.reason,
                                      }
                                    : {}),
                            }));
                            const visit = {
                                preserveScroll: true,
                                onSuccess: () => {
                                    toast.success('Manutenção salva.');
                                    form.setData('status', '');
                                    form.setData('reason', '');
                                },
                                onError: () =>
                                    toast.error(
                                        'Confira os campos e as regras da manutenção.',
                                    ),
                                onFinish: () => {
                                    lock.current = false;
                                },
                            };

                            if (maintenance) {
                                form.patch(update.url(maintenance.id), visit);
                            } else {
                                form.post(store.url(), visit);
                            }
                        }}
                    >
                        <fieldset
                            disabled={form.processing || !editable}
                            className="grid min-w-0 gap-4"
                        >
                            {!maintenance && (
                                <IncidentSelect
                                    id="target_type"
                                    label="Modalidade"
                                    value={form.data.target_type}
                                    options={[
                                        { value: 'unit', label: 'Unidade' },
                                        {
                                            value: 'common_area',
                                            label: 'Área comum',
                                        },
                                    ]}
                                    onChange={(value) =>
                                        form.setData('target_type', value)
                                    }
                                />
                            )}
                            {!maintenance && form.data.target_type === 'unit' && (
                                <IncidentSelect
                                    id="unit"
                                    label="Unidade"
                                    value={form.data.unit_id}
                                    options={options.units}
                                    onChange={(value) =>
                                        form.setData('unit_id', value)
                                    }
                                    error={form.errors.unit_id}
                                />
                            )}
                            {form.data.target_type === 'common_area' && (
                                <IncidentSelect
                                    id="common_area"
                                    label="Área comum"
                                    value={form.data.common_area_id}
                                    options={commonAreas}
                                    onChange={(value) =>
                                        form.setData('common_area_id', value)
                                    }
                                    error={form.errors.common_area_id}
                                />
                            )}
                            <IncidentSelect
                                id="admin"
                                label="Administrador responsável"
                                value={form.data.admin_id}
                                options={admins}
                                onChange={(value) =>
                                    form.setData('admin_id', value)
                                }
                                error={form.errors.admin_id}
                                disabled={!editable || form.processing}
                            />
                            <IncidentSelect
                                id="provider"
                                label="Prestador"
                                value={form.data.service_provider_id}
                                options={providers}
                                onChange={(value) =>
                                    form.setData('service_provider_id', value)
                                }
                                error={form.errors.service_provider_id}
                                disabled={!editable || form.processing}
                            />
                            <div className="grid gap-2">
                                <Label htmlFor="description">Descrição</Label>
                                <textarea
                                    id="description"
                                    required
                                    maxLength={10000}
                                    className="min-h-32 w-full rounded-md border bg-transparent p-3 text-sm"
                                    value={form.data.description}
                                    onChange={(event) =>
                                        form.setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.description} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="scheduled">
                                    {form.data.target_type === 'common_area'
                                        ? 'Data da manutenção e do bloqueio'
                                        : 'Agendamento (opcional enquanto pendente)'}
                                </Label>
                                <Input
                                    id="scheduled"
                                    type="datetime-local"
                                    required={
                                        form.data.target_type === 'common_area'
                                    }
                                    step="1"
                                    value={form.data.scheduled_at}
                                    onChange={(event) =>
                                        form.setData(
                                            'scheduled_at',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.scheduled_at}
                                />
                                {form.data.target_type === 'common_area' && (
                                    <p className="text-sm text-muted-foreground">
                                        A área ficará bloqueada durante todo o
                                        horário operacional desta data. Reservas
                                        pendentes ou aprovadas precisam ser
                                        resolvidas antes.
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="cost">
                                    Custo em R$ (opcional)
                                </Label>
                                <Input
                                    id="cost"
                                    inputMode="decimal"
                                    placeholder="0,00"
                                    value={form.data.cost}
                                    onChange={(event) =>
                                        form.setData('cost', event.target.value)
                                    }
                                />
                                <InputError message={form.errors.cost} />
                            </div>
                            {maintenance && editable && (
                                <>
                                    <IncidentSelect
                                        id="status"
                                        label="Alterar status nesta operação"
                                        value={form.data.status || 'keep'}
                                        options={[
                                            {
                                                value: 'keep',
                                                label: 'Manter status atual',
                                            },
                                            ...maintenance.allowed_statuses,
                                        ]}
                                        onChange={(value) =>
                                            form.setData(
                                                'status',
                                                value === 'keep' ? '' : value,
                                            )
                                        }
                                        error={form.errors.status}
                                        disabled={form.processing}
                                    />
                                    <Label htmlFor="reason">
                                        Motivo (opcional)
                                    </Label>
                                    <Input
                                        id="reason"
                                        maxLength={255}
                                        value={form.data.reason}
                                        onChange={(event) =>
                                            form.setData(
                                                'reason',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={form.errors.reason} />
                                    <p className="text-sm text-muted-foreground">
                                        Ao finalizar, a data de execução será
                                        registrada pelo sistema. O status da
                                        ocorrência permanece independente.
                                    </p>
                                </>
                            )}
                        </fieldset>
                        {editable && (
                            <Button disabled={form.processing}>
                                {form.processing
                                    ? 'Salvando...'
                                    : maintenance
                                      ? 'Salvar alterações'
                                      : 'Cadastrar manutenção'}
                            </Button>
                        )}
                    </form>
                    {maintenance && (
                        <section className="grid min-w-0 content-start gap-4 rounded-xl border p-5">
                            <h2 className="text-lg font-semibold">Histórico</h2>
                            <MaintenanceHistory events={maintenance.history} />
                        </section>
                    )}
                </div>
            </div>
        </>
    );
}
MaintenanceForm.layout = {
    breadcrumbs: [{ title: 'Manutenções', href: index() }],
};
