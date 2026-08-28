import { Head, usePage } from '@inertiajs/react';
import { Building2, CalendarClock } from 'lucide-react';
import { DashboardComingSoonCard } from '@/components/dashboard-coming-soon-card';
import { DashboardWelcomeCard } from '@/components/dashboard-welcome-card';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';
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
            <div className="flex h-full flex-1 flex-col p-4 sm:p-6">
                <main className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                    <DashboardWelcomeCard
                        user={auth.user}
                        roleLabel="Morador"
                        description="Consulte as informações da sua unidade e acompanhe as novidades do condomínio."
                    />

                    <div className="grid gap-6 lg:grid-cols-2">
                        {unit ? (
                            <Card className="border-cosphere-line/80 shadow-sm dark:border-border">
                                <CardHeader>
                                    <h2 className="text-lg font-semibold break-words text-cosphere-navy dark:text-foreground">
                                        Sua unidade
                                    </h2>
                                    <CardDescription>
                                        Informações vinculadas ao seu perfil.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <dl className="grid gap-4 text-sm sm:grid-cols-2">
                                        {unit.block && (
                                            <div className="min-w-0">
                                                <dt className="text-muted-foreground">
                                                    Bloco
                                                </dt>
                                                <dd className="mt-1 font-medium break-words">
                                                    {unit.block}
                                                </dd>
                                            </div>
                                        )}
                                        <div className="min-w-0">
                                            <dt className="text-muted-foreground">
                                                Número
                                            </dt>
                                            <dd className="mt-1 font-medium break-words">
                                                {unit.number}
                                            </dd>
                                        </div>
                                        <div className="min-w-0">
                                            <dt className="text-muted-foreground">
                                                Tipo
                                            </dt>
                                            <dd className="mt-1 font-medium break-words">
                                                {unit.type}
                                            </dd>
                                        </div>
                                        {unit.complement && (
                                            <div className="min-w-0">
                                                <dt className="text-muted-foreground">
                                                    Complemento
                                                </dt>
                                                <dd className="mt-1 font-medium break-words">
                                                    {unit.complement}
                                                </dd>
                                            </div>
                                        )}
                                    </dl>
                                </CardContent>
                            </Card>
                        ) : (
                            <Card className="border-dashed border-cosphere-line/80 shadow-sm dark:border-border">
                                <CardHeader>
                                    <span className="inline-flex size-11 items-center justify-center rounded-xl bg-cosphere-orange/10 text-cosphere-orange">
                                        <Building2
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <h2 className="text-lg font-semibold break-words text-cosphere-navy dark:text-foreground">
                                        Unidade não vinculada
                                    </h2>
                                    <CardDescription className="leading-relaxed break-words">
                                        Ainda não há uma unidade vinculada ao
                                        seu perfil. Entre em contato com a
                                        administração para regularizar o
                                        vínculo.
                                    </CardDescription>
                                </CardHeader>
                            </Card>
                        )}

                        <DashboardComingSoonCard
                            icon={CalendarClock}
                            title="Novidades do condomínio"
                            description="Reservas, visitantes, encomendas e outras informações serão exibidas aqui conforme os módulos forem integrados."
                        />
                    </div>
                </main>
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
