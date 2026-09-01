import { Head, Link, usePage } from '@inertiajs/react';
import { Building2, ContactRound } from 'lucide-react';
import { DashboardWelcomeCard } from '@/components/dashboard-welcome-card';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes/morador';
import { index as visitorsIndex } from '@/routes/morador/visitors';
import type { UnitSummary } from '@/types';

type MoradorDashboardProps = {
    unit: UnitSummary | null;
    active_authorizations: number;
};

export default function MoradorDashboard({
    unit,
    active_authorizations: activeAuthorizations,
}: MoradorDashboardProps) {
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

                        <Card className="border-cosphere-line/80 shadow-sm dark:border-border">
                            <CardHeader>
                                <span className="inline-flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <ContactRound
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <CardTitle>Visitantes</CardTitle>
                                <CardDescription>
                                    Autorizações ativas vinculadas à sua
                                    unidade.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-5">
                                <p className="text-4xl font-semibold tracking-tight text-cosphere-navy dark:text-foreground">
                                    {activeAuthorizations}
                                </p>
                                <Button className="w-full sm:w-fit" asChild>
                                    <Link href={visitorsIndex()}>
                                        Gerenciar visitantes
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
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
