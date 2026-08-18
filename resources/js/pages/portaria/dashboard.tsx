import { Head, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { DashboardComingSoonCard } from '@/components/dashboard-coming-soon-card';
import { DashboardWelcomeCard } from '@/components/dashboard-welcome-card';
import { dashboard } from '@/routes/portaria';

export default function PortariaDashboard() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Dashboard da portaria" />
            <div className="flex h-full flex-1 flex-col p-4 sm:p-6">
                <main className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                    <DashboardWelcomeCard
                        user={auth.user}
                        roleLabel="Porteiro"
                        description="Acompanhe as informações e novidades destinadas à rotina da portaria."
                    />

                    <DashboardComingSoonCard
                        icon={ShieldCheck}
                        title="Rotinas da portaria"
                        description="As informações operacionais de visitantes, controle de acesso e recebimento de encomendas serão disponibilizadas conforme os módulos forem integrados."
                    />
                </main>
            </div>
        </>
    );
}

PortariaDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
