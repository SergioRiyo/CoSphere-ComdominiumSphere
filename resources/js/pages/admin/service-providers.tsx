import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { IncidentSelect } from '@/components/incident-fields';
import InputError from '@/components/input-error';
import ManagementPagination from '@/components/management-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index, show } from '@/routes/admin/service-providers';
import type { Paged, Provider } from '@/types/maintenance';
export default function Providers({
    providers,
    filters,
}: {
    providers: Paged<Provider>;
    filters: { search?: string; state?: string };
}) {
    const form = useForm({
        search: filters.search || '',
        state: filters.state || 'active',
    });
    const [busy, setBusy] = useState(false);

    return (
        <>
            <Head title="Prestadores" />
            <div className="grid gap-6 p-4 sm:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold">Prestadores</h1>
                    <Button asChild>
                        <Link href={create()}>Novo prestador</Link>
                    </Button>
                </header>
                <form
                    className="grid gap-4 rounded-xl border p-4 sm:grid-cols-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.get(index.url(), { preserveScroll: true });
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="provider-search">Nome</Label>
                        <Input
                            id="provider-search"
                            value={form.data.search}
                            onChange={(event) =>
                                form.setData('search', event.target.value)
                            }
                        />
                        <InputError message={form.errors.search} />
                    </div>
                    <IncidentSelect
                        id="provider-state"
                        label="Situação"
                        value={form.data.state}
                        onChange={(value) => form.setData('state', value)}
                        options={[
                            { value: 'active', label: 'Ativos' },
                            { value: 'archived', label: 'Arquivados' },
                            { value: 'all', label: 'Todos' },
                        ]}
                        error={form.errors.state}
                    />
                    <Button className="self-end" disabled={form.processing}>
                        Aplicar filtros
                    </Button>
                </form>
                <p className="text-sm text-muted-foreground">
                    {providers.total} prestador(es)
                </p>
                {providers.data.length === 0 && (
                    <p>
                        Nenhum prestador encontrado para os filtros
                        selecionados.
                    </p>
                )}
                <div className="grid gap-4 lg:grid-cols-2">
                    {providers.data.map((provider) => (
                        <article
                            key={provider.id}
                            className="grid min-w-0 gap-3 rounded-xl border p-5 break-words"
                        >
                            <div className="flex flex-wrap justify-between gap-2">
                                <h2 className="font-semibold">
                                    {provider.name}
                                </h2>
                                <Badge variant="outline">
                                    {provider.deleted_at
                                        ? 'Arquivado'
                                        : 'Ativo'}
                                </Badge>
                            </div>
                            <p>
                                {provider.specialty ||
                                    'Especialidade não informada'}
                            </p>
                            <p className="text-sm">
                                {provider.phone || 'Telefone não informado'} ·{' '}
                                {provider.email || 'E-mail não informado'}
                            </p>
                            <Button asChild variant="outline" className="w-fit">
                                <Link href={show(provider.id)}>
                                    Consultar prestador
                                </Link>
                            </Button>
                        </article>
                    ))}
                </div>
                <ManagementPagination
                    page={providers.current_page}
                    last={providers.last_page}
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
Providers.layout = { breadcrumbs: [{ title: 'Prestadores', href: index() }] };
