import { Head } from '@inertiajs/react';
import { CheckCircle2, Download, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import VisitorInvitationShell from '@/components/visitor-invitation-shell';

export default function Completed({
    qr_svg,
    access_code,
}: {
    qr_svg: string;
    access_code: string;
}) {
    const displayCode = access_code.replace(/(.{4})(?=.)/g, '$1-');
    const download = () => {
        const link = document.createElement('a');
        link.href = URL.createObjectURL(
            new Blob([qr_svg], { type: 'image/svg+xml' }),
        );
        link.download = 'qr-code-acesso.svg';
        link.click();
        URL.revokeObjectURL(link.href);
    };

    return (
        <VisitorInvitationShell>
            <Head title="Acesso gerado" />
            <div className="w-full max-w-xl">
                <section className="grid w-full justify-items-center gap-5 rounded-2xl border border-border/80 bg-card p-6 text-center shadow-sm sm:p-8">
                    <span className="flex size-14 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">
                        <CheckCircle2 className="size-7" aria-hidden="true" />
                    </span>
                    <div className="space-y-2">
                        <h1 className="text-2xl font-semibold">
                            Seu acesso está pronto
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Apresente este QR Code na portaria no dia da visita.
                        </p>
                    </div>
                    <div
                        className="max-w-full rounded-2xl border bg-white p-4 [&>svg]:h-auto [&>svg]:max-w-full"
                        role="img"
                        aria-label="QR Code de acesso à portaria"
                        dangerouslySetInnerHTML={{ __html: qr_svg }}
                    />
                    <div className="w-full rounded-xl border border-border bg-muted/30 px-4 py-4">
                        <p className="text-sm font-medium">
                            Código para a portaria
                        </p>
                        <p className="mt-2 font-mono text-2xl font-bold tracking-wider break-all">
                            {displayCode}
                        </p>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Se o QR Code não puder ser lido, informe este código
                            ao porteiro.
                        </p>
                    </div>
                    <Button
                        className="h-11 w-full sm:w-auto"
                        onClick={download}
                    >
                        <Download aria-hidden="true" />
                        Baixar QR Code
                    </Button>
                    <div className="flex gap-3 rounded-xl border border-primary/20 bg-primary/5 p-4 text-left text-sm">
                        <ShieldCheck
                            className="mt-0.5 size-5 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <p>
                            Baixe o QR Code e anote ou tire uma foto do código
                            antes de sair. O link do convite só pode ser
                            utilizado uma vez.
                        </p>
                    </div>
                </section>
            </div>
        </VisitorInvitationShell>
    );
}
