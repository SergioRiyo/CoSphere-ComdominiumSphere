import { Head, usePage } from '@inertiajs/react';
import { dashboard } from '@/routes/morador';
import type { UnitSummary } from '@/types';

type MoradorDashboardProps = {
    unit: UnitSummary | null;
};

export default function MoradorDashboard({ unit }: MoradorDashboardProps) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Dashboard do morador" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <p className="text-sm font-medium text-muted-foreground">Morador</p>
                    <h1 className="mt-2 text-2xl font-semibold">Olá, {auth.user.name}</h1>
                    <p className="mt-3 text-muted-foreground">
                        Acompanhe aqui as informações da sua unidade e do condomínio.
                    </p>
                </div>

                {unit && (
                    <div className="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                        <h2 className="text-lg font-semibold">Sua unidade</h2>
                        <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                            <div>
                                <dt className="text-muted-foreground">Número</dt>
                                <dd className="font-medium">{unit.number}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Tipo</dt>
                                <dd className="font-medium">{unit.type}</dd>
                            </div>
                            {unit.complement && (
                                <div>
                                    <dt className="text-muted-foreground">Complemento</dt>
                                    <dd className="font-medium">{unit.complement}</dd>
                                </div>
                            )}
                        </dl>
                    </div>
                )}
            </div>
        </>
    );
}

MoradorDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
