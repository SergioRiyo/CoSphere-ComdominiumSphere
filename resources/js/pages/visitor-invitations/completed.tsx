import { Head } from '@inertiajs/react';
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
        <main className="mx-auto grid min-h-svh max-w-xl place-items-center p-4 sm:p-6">
            <Head title="Acesso gerado" />
            <section className="grid w-full justify-items-center gap-5 rounded-2xl border bg-card p-5 text-center shadow-sm sm:p-6">
                <h1 className="text-xl font-semibold">
                    Seu acesso está pronto
                </h1>
                <div
                    className="max-w-full bg-white p-3 [&>svg]:h-auto [&>svg]:max-w-full"
                    dangerouslySetInnerHTML={{ __html: qr_svg }}
                />
                <p className="text-sm text-muted-foreground">
                    Guarde este QR Code para apresentar na portaria.
                </p>
                <Button className="w-full sm:w-auto" onClick={download}>
                    Baixar QR Code
                </Button>
            </section>
        </main>
    );
}
