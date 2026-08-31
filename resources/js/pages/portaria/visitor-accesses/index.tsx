import { Head, router } from '@inertiajs/react';
import {
    CarFront,
    Clock3,
    DoorOpen,
    LogOut,
    ShieldCheck,
    UserRound,
    UsersRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import {
    exit as registerExit,
    index,
} from '@/routes/portaria/visitor-accesses';
import type { PortariaOpenVisitorAccess } from '@/types';

type VisitorAccessesPageProps = {
    openAccesses: PortariaOpenVisitorAccess[];
    timezone: string;
};

export default function VisitorAccessesPage({
    openAccesses,
    timezone,
}: VisitorAccessesPageProps) {
    const [selectedAccess, setSelectedAccess] =
        useState<PortariaOpenVisitorAccess | null>(null);
    const [exitingAccessId, setExitingAccessId] = useState<number | null>(null);
    const exitSubmissionRef = useRef<number | null>(null);
    const isProcessingExit = exitingAccessId !== null;

    const handleDialogOpenChange = (open: boolean) => {
        if (!open && !isProcessingExit) {
            setSelectedAccess(null);
        }
    };

    const submitExit = () => {
        if (selectedAccess === null || exitSubmissionRef.current !== null) {
            return;
        }

        const accessId = selectedAccess.id;

        exitSubmissionRef.current = accessId;
        setExitingAccessId(accessId);

        router.post(
            registerExit.url(accessId),
            {},
            {
                preserveScroll: true,
                onSuccess: () => setSelectedAccess(null),
                onFinish: () => {
                    exitSubmissionRef.current = null;
                    setExitingAccessId(null);
                },
            },
        );
    };

    return (
        <>
            <Head title="Visitantes presentes" />

            <div className="flex h-full flex-1 flex-col p-4 sm:p-6">
                <main className="mx-auto flex w-full max-w-6xl flex-col gap-6">
                    <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div className="flex max-w-3xl flex-col gap-2">
                            <div className="flex items-center gap-2 text-sm font-medium text-primary">
                                <ShieldCheck
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Operação da portaria
                            </div>
                            <h1 className="text-3xl font-semibold tracking-tight">
                                Visitantes presentes
                            </h1>
                            <p className="text-sm leading-relaxed text-muted-foreground">
                                Acompanhe as entradas em aberto e registre a
                                saída de cada visitante.
                            </p>
                        </div>

                        <Badge
                            variant="secondary"
                            className="w-fit gap-2 px-3 py-1.5 text-sm"
                        >
                            <UsersRound className="size-4" aria-hidden="true" />
                            {presentCountLabel(openAccesses.length)}
                        </Badge>
                    </header>

                    {openAccesses.length === 0 ? (
                        <EmptyState />
                    ) : (
                        <section
                            className="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                            aria-label="Acessos abertos"
                        >
                            {openAccesses.map((access) => (
                                <VisitorAccessCard
                                    key={access.id}
                                    access={access}
                                    timezone={timezone}
                                    disabled={isProcessingExit}
                                    onRegisterExit={() =>
                                        setSelectedAccess(access)
                                    }
                                />
                            ))}
                        </section>
                    )}
                </main>
            </div>

            <Dialog
                open={selectedAccess !== null}
                onOpenChange={handleDialogOpenChange}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirmar saída</DialogTitle>
                        <DialogDescription className="leading-relaxed">
                            {selectedAccess === null
                                ? 'Confirme a saída do visitante.'
                                : `Registrar a saída de ${selectedAccess.visitor_name} (${formatUnit(selectedAccess.unit)})?`}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant="secondary"
                                disabled={isProcessingExit}
                            >
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={isProcessingExit}
                            onClick={submitExit}
                        >
                            {isProcessingExit ? (
                                <>
                                    <Spinner />
                                    Registrando saída...
                                </>
                            ) : (
                                <>
                                    <LogOut />
                                    Confirmar saída
                                </>
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function VisitorAccessCard({
    access,
    timezone,
    disabled,
    onRegisterExit,
}: {
    access: PortariaOpenVisitorAccess;
    timezone: string;
    disabled: boolean;
    onRegisterExit: () => void;
}) {
    return (
        <Card className="min-w-0 gap-0 overflow-hidden py-0 shadow-sm">
            <CardHeader className="gap-4 border-b bg-muted/30 px-5 py-5">
                <div className="flex min-w-0 items-start justify-between gap-3">
                    <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                        <UserRound className="size-5" aria-hidden="true" />
                    </span>
                    <Badge className="bg-emerald-600 text-white hover:bg-emerald-600">
                        Presente
                    </Badge>
                </div>
                <div className="min-w-0">
                    <CardTitle className="text-lg break-words">
                        {access.visitor_name}
                    </CardTitle>
                    <CardDescription className="mt-1 break-words">
                        {formatUnit(access.unit)}
                    </CardDescription>
                </div>
            </CardHeader>

            <CardContent className="grid flex-1 gap-5 px-5 py-5">
                <dl className="grid gap-4 text-sm">
                    <AccessDetail
                        icon={Clock3}
                        label="Entrada"
                        value={formatDateTime(access.entry_time, timezone)}
                    />
                    <AccessDetail
                        icon={ShieldCheck}
                        label="Porteiro da entrada"
                        value={access.entry_doorman_name ?? 'Não identificado'}
                    />
                    <AccessDetail
                        icon={CarFront}
                        label="Placa"
                        value={access.vehicle_plate ?? 'Não informada'}
                    />
                </dl>

                <Button
                    type="button"
                    variant="outline"
                    size="lg"
                    className="mt-auto w-full"
                    disabled={disabled}
                    onClick={onRegisterExit}
                >
                    <LogOut />
                    Registrar saída
                </Button>
            </CardContent>
        </Card>
    );
}

function AccessDetail({
    icon: Icon,
    label,
    value,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
}) {
    return (
        <div className="flex min-w-0 items-start gap-3">
            <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-muted text-muted-foreground">
                <Icon className="size-4" aria-hidden="true" />
            </span>
            <div className="min-w-0">
                <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </dt>
                <dd className="mt-1 font-medium break-words">{value}</dd>
            </div>
        </div>
    );
}

function EmptyState() {
    return (
        <Card className="border-dashed bg-muted/20">
            <CardContent className="grid place-items-center gap-4 px-6 py-16 text-center">
                <span className="grid size-14 place-items-center rounded-full bg-muted text-muted-foreground">
                    <DoorOpen className="size-7" aria-hidden="true" />
                </span>
                <div className="grid gap-1">
                    <p className="font-medium">
                        Nenhum visitante presente no momento.
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Novas entradas aparecerão aqui enquanto estiverem em
                        aberto.
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}

function formatUnit(unit: PortariaOpenVisitorAccess['unit']) {
    return unit.block
        ? `Bloco ${unit.block} · Unidade ${unit.number}`
        : `Unidade ${unit.number}`;
}

function formatDateTime(value: string, timezone: string) {
    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(value));
}

function presentCountLabel(count: number) {
    return count === 1
        ? '1 visitante presente'
        : `${count} visitantes presentes`;
}

VisitorAccessesPage.layout = {
    breadcrumbs: [
        {
            title: 'Visitantes presentes',
            href: index(),
        },
    ],
};
