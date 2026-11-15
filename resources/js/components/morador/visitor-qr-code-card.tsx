import { Check, Copy, Download, QrCode } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useClipboard } from '@/hooks/use-clipboard';
import { accessCode, qrCode } from '@/routes/morador/visitors';

export default function VisitorQrCodeCard({
    authorizationId,
}: {
    authorizationId: number;
}) {
    const [, copy] = useClipboard();
    const [isCopying, setIsCopying] = useState(false);
    const [wasCopied, setWasCopied] = useState(false);
    const [attempt, setAttempt] = useState(0);
    const [codeResult, setCodeResult] = useState<{
        authorizationId: number;
        code: string | null;
    } | null>(null);
    const currentResult =
        codeResult?.authorizationId === authorizationId ? codeResult : null;
    const manualCode = currentResult?.code ?? null;
    const displayCode =
        manualCode && /^[A-HJ-NP-Z2-9]{12}$/.test(manualCode)
            ? manualCode.replace(/(.{4})(?=.)/g, '$1-')
            : manualCode;

    useEffect(() => {
        const controller = new AbortController();

        const loadCode = async () => {
            try {
                const response = await fetch(accessCode.url(authorizationId), {
                    credentials: 'same-origin',
                    headers: { Accept: 'text/plain' },
                    cache: 'no-store',
                    signal: controller.signal,
                });

                if (
                    !response.ok ||
                    !response.headers
                        .get('content-type')
                        ?.startsWith('text/plain')
                ) {
                    throw new Error('Código indisponível');
                }

                const code = (await response.text()).trim();

                if (!code) {
                    throw new Error('Código indisponível');
                }

                if (!controller.signal.aborted) {
                    setCodeResult({ authorizationId, code });
                }
            } catch {
                if (!controller.signal.aborted) {
                    setCodeResult({ authorizationId, code: null });
                }
            }
        };

        void loadCode();

        return () => controller.abort();
    }, [authorizationId, attempt]);

    const copyManualCode = async () => {
        setIsCopying(true);

        try {
            if (!manualCode || !(await copy(manualCode))) {
                throw new Error('Não foi possível copiar o código.');
            }

            setWasCopied(true);
            toast.success('Código manual copiado.');
        } catch {
            toast.error('Não foi possível copiar o código manual.');
        } finally {
            setIsCopying(false);
        }
    };

    return (
        <Card className="overflow-hidden border-primary/20 bg-gradient-to-br from-card via-card to-primary/5">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <QrCode className="size-5 text-primary" />
                    QR Code de acesso
                </CardTitle>
                <CardDescription>
                    Apresente este código na portaria ou use o código manual
                    quando a câmera não estiver disponível.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-6 sm:grid-cols-[minmax(12rem,16rem)_minmax(0,1fr)] sm:items-center">
                <div className="mx-auto rounded-2xl bg-white p-3 shadow-sm ring-1 ring-black/5">
                    <img
                        src={qrCode.url(authorizationId)}
                        alt="QR Code para acesso à portaria"
                        className="size-52"
                    />
                </div>
                <div className="grid gap-3">
                    <div
                        className="rounded-xl border bg-background/70 p-4"
                        aria-live="polite"
                    >
                        <p className="text-sm font-medium">
                            Código para a portaria
                        </p>
                        {displayCode ? (
                            <p className="mt-2 font-mono text-xl font-semibold tracking-wider break-all">
                                {displayCode}
                            </p>
                        ) : currentResult ? (
                            <div className="mt-2 flex flex-wrap items-center gap-2">
                                <p className="text-sm text-destructive">
                                    Não foi possível carregar o código.
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        setCodeResult(null);
                                        setAttempt((value) => value + 1);
                                    }}
                                >
                                    Tentar novamente
                                </Button>
                            </div>
                        ) : (
                            <p className="mt-2 text-sm text-muted-foreground">
                                Carregando código...
                            </p>
                        )}
                    </div>
                    <p className="text-sm text-muted-foreground">
                        O QR Code contém somente um identificador de acesso, sem
                        dados pessoais do visitante.
                    </p>
                    <Button variant="outline" asChild>
                        <a
                            href={qrCode.url(authorizationId, {
                                query: { download: 1 },
                            })}
                            download
                        >
                            <Download />
                            Baixar QR Code
                        </a>
                    </Button>
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={copyManualCode}
                        disabled={isCopying || !manualCode}
                    >
                        {wasCopied ? <Check /> : <Copy />}
                        {isCopying
                            ? 'Copiando...'
                            : wasCopied
                              ? 'Código copiado'
                              : 'Copiar código manual'}
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
