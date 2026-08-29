import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarClock,
    Car,
    IdCard,
    MapPin,
    Phone,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { VisitorStatusBadge } from '@/components/visitor-status-badge';
import { index } from '@/routes/morador/visitors';
import type { VisitorAuthorizationDetails } from '@/types';

type VisitorDetailsPageProps = {
    authorization: VisitorAuthorizationDetails;
    timezone: string;
};

export default function VisitorDetailsPage({
    authorization,
    timezone,
}: VisitorDetailsPageProps) {
    const unit = authorization.unit.block
        ? `Bloco ${authorization.unit.block} · Unidade ${authorization.unit.number}`
        : `Unidade ${authorization.unit.number}`;

    return (
        <>
            <Head title="Detalhes da visita" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex max-w-3xl flex-col gap-2">
                        <p className="text-sm font-medium text-primary">
                            Autorização #{authorization.id}
                        </p>
                        <h1 className="text-3xl font-semibold tracking-tight">
                            {authorization.visitor?.name ?? 'Cadastro pendente'}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Informações da visita vinculada à sua unidade.
                        </p>
                    </div>
                    <VisitorStatusBadge
                        status={authorization.status}
                        label={authorization.status_label}
                    />
                </header>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(18rem,0.85fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Visitante</CardTitle>
                            <CardDescription>
                                Dados informados para esta autorização.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {authorization.visitor ? (
                                <dl className="grid gap-5 sm:grid-cols-2">
                                    <Detail
                                        icon={UserRound}
                                        label="Nome completo"
                                        value={authorization.visitor.name}
                                    />
                                    <Detail
                                        icon={IdCard}
                                        label="CPF"
                                        value={authorization.visitor.cpf}
                                    />
                                    <Detail
                                        icon={Phone}
                                        label="Telefone"
                                        value={
                                            authorization.visitor.phone || '—'
                                        }
                                    />
                                    <Detail
                                        icon={Car}
                                        label="Veículo"
                                        value={
                                            authorization.vehicle_plate ||
                                            'Não informado'
                                        }
                                    />
                                </dl>
                            ) : (
                                <div className="rounded-xl border border-dashed p-6 text-center">
                                    <p className="font-medium">
                                        Dados ainda não preenchidos
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        O visitante ainda precisa concluir o
                                        cadastro pelo convite.
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="bg-muted/30">
                        <CardHeader>
                            <CardTitle>Período autorizado</CardTitle>
                            <CardDescription>
                                Janela de acesso definida para a visita.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-5">
                                <Detail
                                    icon={CalendarClock}
                                    label="Início"
                                    value={formatDateTime(
                                        authorization.start_date,
                                        timezone,
                                    )}
                                />
                                <Detail
                                    icon={CalendarClock}
                                    label="Término"
                                    value={formatDateTime(
                                        authorization.end_date,
                                        timezone,
                                    )}
                                />
                                <Detail
                                    icon={MapPin}
                                    label="Unidade"
                                    value={unit}
                                />
                            </dl>
                        </CardContent>
                    </Card>
                </div>

                <div>
                    <Button variant="outline" asChild>
                        <Link href={index()}>
                            <ArrowLeft />
                            Voltar para visitantes
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

function Detail({
    icon: Icon,
    label,
    value,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
}) {
    return (
        <div className="flex gap-3">
            <div className="grid size-9 shrink-0 place-items-center rounded-lg bg-background shadow-sm ring-1 ring-border">
                <Icon className="size-4 text-primary" />
            </div>
            <div className="min-w-0">
                <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </dt>
                <dd className="mt-1 text-sm font-medium break-words">
                    {value}
                </dd>
            </div>
        </div>
    );
}

function formatDateTime(value: string, timezone: string) {
    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'long',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(value));
}

VisitorDetailsPage.layout = {
    breadcrumbs: [
        {
            title: 'Visitantes',
            href: index(),
        },
    ],
};
