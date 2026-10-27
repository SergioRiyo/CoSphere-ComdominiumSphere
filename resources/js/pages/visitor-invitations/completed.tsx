import { Head } from '@inertiajs/react';
import CoSphereLogo from '@/components/cosphere-logo';
import ThemeToggle from '@/components/theme-toggle';
import { Button } from '@/components/ui/button';

export default function Completed({ qr_svg }: { qr_svg: string }) {
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
        <main className="relative grid min-h-svh place-items-center bg-background p-4 sm:p-6">
            <Head title="Acesso gerado" />
            <ThemeToggle className="absolute top-4 right-4 sm:top-6 sm:right-6" />
            <div className="w-full max-w-xl">
                <div className="mb-5 flex justify-center">
                    <CoSphereLogo size="md" withTagline />
                </div>
                <section className="grid w-full justify-items-center gap-5 rounded-3xl border border-border/80 bg-card/95 p-5 text-center shadow-cosphere-panel backdrop-blur sm:p-7">
                    <h1 className="text-xl font-semibold">
                        Seu acesso está pronto
                    </h1>
                    <div
                        className="max-w-full rounded-2xl bg-white p-3 [&>svg]:h-auto [&>svg]:max-w-full"
                        dangerouslySetInnerHTML={{ __html: qr_svg }}
                    />
                    <p className="text-sm text-muted-foreground">
                        Guarde este QR Code para apresentar na portaria.
                    </p>
                    <Button className="w-full sm:w-auto" onClick={download}>
                        Baixar QR Code
                    </Button>
                </section>
            </div>
        </main>
    );
}
