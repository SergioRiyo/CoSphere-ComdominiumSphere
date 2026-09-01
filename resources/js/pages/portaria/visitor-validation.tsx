import { Head, useHttp } from '@inertiajs/react';
import {
    CalendarClock,
    CarFront,
    CircleCheckBig,
    CircleX,
    Clock3,
    KeyRound,
    LogIn,
    MapPin,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import VisitorQrScanner from '@/components/portaria/visitor-qr-scanner';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes/portaria';
import { store as storeEntry } from '@/routes/portaria/visitor-accesses';
import { validate, validation } from '@/routes/portaria/visitor-authorizations';
import type {
    PortariaEntryResult,
    PortariaValidatedAuthorization,
    PortariaValidationResult,
} from '@/types';

type VisitorValidationPageProps = {
    timezone: string;
};

type ValidationFormData = {
    access_code: string;
};

export default function VisitorValidationPage({
    timezone,
}: VisitorValidationPageProps) {
    const { data, setData, submit, processing, errors, clearErrors } = useHttp<
        ValidationFormData,
        PortariaValidationResult
    >({
        access_code: '',
    });
    const {
        setData: setEntryData,
        submit: submitEntry,
        processing: entryProcessing,
        errors: entryErrors,
        clearErrors: clearEntryErrors,
    } = useHttp<ValidationFormData, PortariaEntryResult>({
        access_code: '',
    });
    const [result, setResult] = useState<PortariaValidationResult | null>(null);
    const [entryResult, setEntryResult] = useState<PortariaEntryResult | null>(
        null,
    );
    const [requestError, setRequestError] = useState<string | null>(null);
    const validationSubmissionRef = useRef(false);
    const entrySubmissionRef = useRef(false);
    const isProcessing = processing || entryProcessing;
    const accessCodeError = errors.access_code ?? entryErrors.access_code;

    const handleCodeChange = (accessCode: string) => {
        setData('access_code', accessCode);
        setEntryData('access_code', accessCode);
        clearErrors('access_code');
        clearEntryErrors('access_code');
        setResult(null);
        setEntryResult(null);
        setRequestError(null);
    };

    const validateAccessCode = async (accessCode: string) => {
        if (isProcessing || validationSubmissionRef.current) {
            return;
        }

        validationSubmissionRef.current = true;
        setData('access_code', accessCode);
        setEntryData('access_code', accessCode);
        clearErrors();
        clearEntryErrors();
        setResult(null);
        setEntryResult(null);
        setRequestError(null);

        try {
            const validationResult = await submit(validate(), {
                onHttpException: (response) => {
                    setRequestError(
                        response.status === 429
                            ? 'Muitas tentativas de validação. Aguarde um instante e tente novamente.'
                            : 'Não foi possível validar o código neste momento.',
                    );
                },
                onNetworkError: () => {
                    setRequestError(
                        'Não foi possível conectar ao servidor. Verifique a conexão e tente novamente.',
                    );
                },
            });

            setResult(validationResult);
        } catch {
            setResult(null);
        } finally {
            validationSubmissionRef.current = false;
        }
    };

    const submitValidation = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        await validateAccessCode(data.access_code);
    };

    const registerEntry = async () => {
        if (isProcessing || entrySubmissionRef.current) {
            return;
        }

        entrySubmissionRef.current = true;
        clearEntryErrors();
        setEntryResult(null);
        setRequestError(null);

        try {
            const nextEntryResult = await submitEntry(storeEntry(), {
                onHttpException: (response) => {
                    setRequestError(
                        response.status === 429
                            ? 'Muitas tentativas de registro. Aguarde um instante e tente novamente.'
                            : 'Não foi possível registrar a entrada neste momento.',
                    );
                },
                onNetworkError: () => {
                    setRequestError(
                        'Não foi possível conectar ao servidor. Verifique a conexão e tente novamente.',
                    );
                },
            });

            setEntryResult(nextEntryResult);
        } catch {
            setEntryResult(null);
        } finally {
            entrySubmissionRef.current = false;
        }
    };

    return (
        <>
            <Head title="Validação de visitante" />

            <div className="flex h-full flex-1 flex-col p-4 sm:p-6">
                <main className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                    <header className="flex max-w-3xl flex-col gap-2">
                        <div className="flex items-center gap-2 text-sm font-medium text-primary">
                            <ShieldCheck
                                className="size-4"
                                aria-hidden="true"
                            />
                            Operação da portaria
                        </div>
                        <h1 className="text-3xl font-semibold tracking-tight">
                            Validação de visitante
                        </h1>
                        <p className="text-sm leading-relaxed text-muted-foreground">
                            Informe o código apresentado pelo visitante para
                            conferir a autorização. A validação não registra a
                            entrada automaticamente.
                        </p>
                    </header>

                    <div className="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:items-start">
                        <Card className="min-w-0 border-cosphere-line/80 shadow-cosphere-soft dark:border-border">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <KeyRound
                                        className="size-5 text-cosphere-orange"
                                        aria-hidden="true"
                                    />
                                    Código de acesso
                                </CardTitle>
                                <CardDescription>
                                    Digite o código completo ou leia o QR Code
                                    para consultar a autorização existente.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form
                                    onSubmit={submitValidation}
                                    className="grid gap-4"
                                    aria-busy={isProcessing}
                                >
                                    <div className="grid gap-2">
                                        <Label htmlFor="access-code">
                                            Código apresentado
                                        </Label>
                                        <Input
                                            id="access-code"
                                            name="access_code"
                                            value={data.access_code}
                                            onChange={(event) =>
                                                handleCodeChange(
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Ex.: csa_..."
                                            required
                                            autoComplete="off"
                                            autoCapitalize="none"
                                            spellCheck={false}
                                            aria-invalid={Boolean(
                                                accessCodeError,
                                            )}
                                            aria-describedby="access-code-error"
                                            className="h-11 font-mono text-base"
                                            disabled={isProcessing}
                                            autoFocus
                                        />
                                        <InputError
                                            id="access-code-error"
                                            message={accessCodeError}
                                        />
                                    </div>

                                    <Button
                                        type="submit"
                                        size="lg"
                                        disabled={isProcessing}
                                        className="w-full"
                                    >
                                        {processing ? (
                                            <>
                                                <Spinner />
                                                Validando...
                                            </>
                                        ) : (
                                            <>
                                                <ShieldCheck />
                                                Validar autorização
                                            </>
                                        )}
                                    </Button>
                                </form>

                                <div
                                    className="my-5 flex items-center gap-3"
                                    aria-hidden="true"
                                >
                                    <span className="h-px flex-1 bg-border" />
                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        ou
                                    </span>
                                    <span className="h-px flex-1 bg-border" />
                                </div>

                                <VisitorQrScanner
                                    disabled={isProcessing}
                                    hasPreviousResult={Boolean(
                                        result ||
                                        entryResult ||
                                        requestError ||
                                        accessCodeError,
                                    )}
                                    onDetected={validateAccessCode}
                                />

                                <p className="sr-only" role="status">
                                    {processing
                                        ? 'Validando autorização.'
                                        : entryProcessing
                                          ? 'Registrando entrada.'
                                          : ''}
                                </p>

                                <p className="mt-3 text-center text-xs leading-relaxed text-muted-foreground">
                                    A câmera exige HTTPS em ambiente publicado.
                                    O código manual continuará disponível.
                                </p>
                            </CardContent>
                        </Card>

                        <section
                            className="min-w-0"
                            aria-live="polite"
                            aria-label="Resultado da validação"
                        >
                            {requestError ? (
                                <Alert
                                    variant="destructive"
                                    className="border-destructive/30 bg-destructive/5"
                                >
                                    <CircleX aria-hidden="true" />
                                    <AlertTitle>Falha na consulta</AlertTitle>
                                    <AlertDescription>
                                        {requestError}
                                    </AlertDescription>
                                </Alert>
                            ) : accessCodeError ? (
                                <DeniedResult message={accessCodeError} />
                            ) : entryResult?.registered && result?.allowed ? (
                                <EntryRegisteredResult
                                    authorization={result.authorization}
                                    message={entryResult.message}
                                    entryTime={entryResult.entry.entry_time}
                                    timezone={timezone}
                                />
                            ) : entryResult ? (
                                <EntryDeniedResult
                                    message={entryResult.message}
                                />
                            ) : result?.allowed ? (
                                <AllowedResult
                                    authorization={result.authorization}
                                    message={result.message}
                                    timezone={timezone}
                                    onRegisterEntry={registerEntry}
                                    entryProcessing={entryProcessing}
                                />
                            ) : result ? (
                                <DeniedResult message={result.message} />
                            ) : (
                                <InitialResult />
                            )}
                        </section>
                    </div>
                </main>
            </div>
        </>
    );
}

function InitialResult() {
    return (
        <Card className="min-w-0 border-dashed bg-muted/30">
            <CardHeader>
                <span className="grid size-11 place-items-center rounded-xl bg-primary/10 text-primary">
                    <ShieldCheck className="size-5" aria-hidden="true" />
                </span>
                <CardTitle>Aguardando validação</CardTitle>
                <CardDescription className="leading-relaxed">
                    O resultado aparecerá aqui com as informações necessárias
                    para conferência.
                </CardDescription>
            </CardHeader>
        </Card>
    );
}

function AllowedResult({
    authorization,
    message,
    timezone,
    onRegisterEntry,
    entryProcessing,
}: {
    authorization: PortariaValidatedAuthorization;
    message: string;
    timezone: string;
    onRegisterEntry: () => void;
    entryProcessing: boolean;
}) {
    return (
        <Card className="min-w-0 border-emerald-500/35 bg-emerald-50/60 shadow-sm dark:bg-emerald-950/15">
            <CardHeader className="gap-3">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <span className="grid size-12 place-items-center rounded-full bg-emerald-600 text-white shadow-sm">
                        <CircleCheckBig className="size-7" aria-hidden="true" />
                    </span>
                    <Badge className="bg-emerald-600 text-white hover:bg-emerald-600">
                        Autorização válida
                    </Badge>
                </div>
                <div className="grid gap-1">
                    <CardTitle className="text-xl text-emerald-950 dark:text-emerald-100">
                        ACESSO LIBERADO
                    </CardTitle>
                    <CardDescription className="text-emerald-800 dark:text-emerald-200">
                        {message}
                    </CardDescription>
                </div>
            </CardHeader>
            <CardContent>
                <dl className="grid min-w-0 gap-5 sm:grid-cols-2">
                    <ResultDetail
                        icon={UserRound}
                        label="Visitante"
                        value={authorization.visitor_name}
                    />
                    <ResultDetail
                        icon={MapPin}
                        label="Unidade"
                        value={formatUnit(authorization.unit)}
                    />
                    <ResultDetail
                        icon={CarFront}
                        label="Placa"
                        value={authorization.vehicle_plate || 'Não informada'}
                    />
                    <ResultDetail
                        icon={CalendarClock}
                        label="Início"
                        value={formatDateTime(
                            authorization.start_date,
                            timezone,
                        )}
                    />
                    <ResultDetail
                        icon={CalendarClock}
                        label="Término"
                        value={formatDateTime(authorization.end_date, timezone)}
                        className="sm:col-span-2"
                    />
                </dl>

                <div className="mt-6 grid gap-3 border-t border-emerald-500/20 pt-6">
                    <p className="text-sm leading-relaxed text-emerald-900 dark:text-emerald-100">
                        Confira os dados acima antes de liberar fisicamente o
                        acesso do visitante.
                    </p>
                    <Button
                        type="button"
                        size="lg"
                        onClick={onRegisterEntry}
                        disabled={entryProcessing}
                        className="w-full bg-emerald-700 text-white hover:bg-emerald-800"
                    >
                        {entryProcessing ? (
                            <>
                                <Spinner />
                                Registrando entrada...
                            </>
                        ) : (
                            <>
                                <LogIn />
                                Registrar entrada
                            </>
                        )}
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

function EntryRegisteredResult({
    authorization,
    message,
    entryTime,
    timezone,
}: {
    authorization: PortariaValidatedAuthorization;
    message: string;
    entryTime: string;
    timezone: string;
}) {
    return (
        <Card className="min-w-0 border-emerald-500/35 bg-emerald-50/60 shadow-sm dark:bg-emerald-950/15">
            <CardHeader className="gap-3">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <span className="grid size-12 place-items-center rounded-full bg-emerald-700 text-white shadow-sm">
                        <CircleCheckBig className="size-7" aria-hidden="true" />
                    </span>
                    <Badge className="bg-emerald-700 text-white hover:bg-emerald-700">
                        Entrada confirmada
                    </Badge>
                </div>
                <div className="grid gap-1">
                    <CardTitle className="text-xl text-emerald-950 dark:text-emerald-100">
                        ENTRADA REGISTRADA
                    </CardTitle>
                    <CardDescription className="text-emerald-800 dark:text-emerald-200">
                        {message}
                    </CardDescription>
                </div>
            </CardHeader>
            <CardContent>
                <dl className="grid min-w-0 gap-5 sm:grid-cols-2">
                    <ResultDetail
                        icon={UserRound}
                        label="Visitante"
                        value={authorization.visitor_name}
                    />
                    <ResultDetail
                        icon={MapPin}
                        label="Unidade"
                        value={formatUnit(authorization.unit)}
                    />
                    <ResultDetail
                        icon={Clock3}
                        label="Horário da entrada"
                        value={formatDateTime(entryTime, timezone)}
                        className="sm:col-span-2"
                    />
                </dl>
            </CardContent>
        </Card>
    );
}

function EntryDeniedResult({ message }: { message: string }) {
    return (
        <Alert
            variant="destructive"
            className="border-destructive/35 bg-destructive/5 px-5 py-5"
        >
            <CircleX className="size-5" aria-hidden="true" />
            <AlertTitle className="text-lg">ENTRADA NÃO REGISTRADA</AlertTitle>
            <AlertDescription className="text-sm leading-relaxed">
                {message}
            </AlertDescription>
        </Alert>
    );
}

function DeniedResult({ message }: { message: string }) {
    return (
        <Alert
            variant="destructive"
            className="border-destructive/35 bg-destructive/5 px-5 py-5"
        >
            <CircleX className="size-5" aria-hidden="true" />
            <AlertTitle className="text-lg">ACESSO NEGADO</AlertTitle>
            <AlertDescription className="text-sm leading-relaxed">
                {message}
            </AlertDescription>
        </Alert>
    );
}

function ResultDetail({
    icon: Icon,
    label,
    value,
    className,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
    className?: string;
}) {
    return (
        <div className={`flex min-w-0 gap-3 ${className ?? ''}`}>
            <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-background/80 shadow-sm ring-1 ring-emerald-500/20">
                <Icon className="size-4 text-emerald-700" aria-hidden="true" />
            </span>
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

function formatUnit(unit: PortariaValidatedAuthorization['unit']) {
    return unit.block
        ? `Bloco ${unit.block} · Unidade ${unit.number}`
        : `Unidade ${unit.number}`;
}

function formatDateTime(value: string, timezone: string) {
    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'long',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(value));
}

VisitorValidationPage.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Validação de visitante',
            href: validation(),
        },
    ],
};
