import { Check, Copy, Download, QrCode } from 'lucide-react';
import { useState } from 'react';
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

    const copyManualCode = async () => {
        setIsCopying(true);

        try {
            const response = await fetch(accessCode.url(authorizationId), {
                credentials: 'same-origin',
                headers: { Accept: 'text/plain' },
            });

            if (!response.ok || !(await copy(await response.text()))) {
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
                        disabled={isCopying}
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
