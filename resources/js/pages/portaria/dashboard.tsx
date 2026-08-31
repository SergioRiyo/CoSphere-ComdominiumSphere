import { Head, Link, usePage } from '@inertiajs/react';
import { ClipboardCheck, History, ShieldCheck, UsersRound } from 'lucide-react';
import { DashboardWelcomeCard } from '@/components/dashboard-welcome-card';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes/portaria';
import { index as visitorAccessHistoryIndex } from '@/routes/portaria/visitor-access-history';
import { index as visitorAccessesIndex } from '@/routes/portaria/visitor-accesses';
import { validation } from '@/routes/portaria/visitor-authorizations';

type PortariaDashboardProps = {
    present_visitors: number;
};

export default function PortariaDashboard({
    present_visitors: presentVisitors,
}: PortariaDashboardProps) {
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

                    <div className="grid gap-6 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
                        <Card className="border-cosphere-line/80 shadow-sm dark:border-border">
                            <CardHeader>
                                <span className="inline-flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <UsersRound
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <CardTitle>Visitantes presentes</CardTitle>
                                <CardDescription>
                                    Entradas registradas que ainda não possuem
                                    saída.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p className="text-4xl font-semibold tracking-tight text-cosphere-navy dark:text-foreground">
                                    {presentVisitors}
                                </p>
                            </CardContent>
                        </Card>

                        <Card className="border-cosphere-line/80 shadow-sm dark:border-border">
                            <CardHeader>
                                <span className="inline-flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <ShieldCheck
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <CardTitle>Controle de acesso</CardTitle>
                                <CardDescription>
                                    Valide autorizações e acompanhe a rotina de
                                    visitantes.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                                <Button className="w-full sm:w-auto" asChild>
                                    <Link href={validation()}>
                                        <ClipboardCheck />
                                        Validar visitante
                                    </Link>
                                </Button>
                                <Button
                                    className="w-full sm:w-auto"
                                    variant="outline"
                                    asChild
                                >
                                    <Link href={visitorAccessesIndex()}>
                                        <UsersRound />
                                        Ver presentes
                                    </Link>
                                </Button>
                                <Button
                                    className="w-full sm:w-auto"
                                    variant="outline"
                                    asChild
                                >
                                    <Link href={visitorAccessHistoryIndex()}>
                                        <History />
                                        Ver histórico
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

PortariaDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
