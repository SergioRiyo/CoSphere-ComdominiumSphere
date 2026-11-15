import { Head } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import VisitorInvitationShell from '@/components/visitor-invitation-shell';

export default function InvitationUnavailable() {
    return (
        <VisitorInvitationShell>
            <Head title="Convite indisponível" />
            <section className="grid w-full max-w-lg justify-items-center gap-5 rounded-2xl border border-border/80 bg-card p-6 text-center shadow-sm sm:p-8">
                <span className="flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <CircleAlert className="size-7" aria-hidden="true" />
                </span>
                <div className="space-y-2">
                    <h1 className="text-2xl font-semibold">
                        Convite indisponível
                    </h1>
                    <p className="text-sm leading-relaxed text-muted-foreground">
                        Este link pode estar incorreto, expirado ou já ter sido
                        utilizado.
                    </p>
                </div>
                <p className="rounded-xl border bg-muted/30 p-4 text-sm">
                    Entre em contato com o morador que enviou o convite e
                    solicite um novo link.
                </p>
            </section>
        </VisitorInvitationShell>
    );
}
