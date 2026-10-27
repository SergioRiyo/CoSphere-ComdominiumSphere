import { Head, Link } from '@inertiajs/react';
import {
    ChartNoAxesCombined,
    MessageSquare,
    ShieldCheck,
    Users,
} from 'lucide-react';
import CoSphereLogo from '@/components/cosphere-logo';
import { Button } from '@/components/ui/button';
import WelcomeFeatureCard from '@/components/welcome-feature-card';
import { home, login } from '@/routes';
import condominioNoite from '../../assets/condominio-noite.jpg';

const features = [
    {
        icon: Users,
        title: 'Moradores e unidades',
        description:
            'Informações de moradores, unidades e responsáveis organizadas em um só lugar.',
    },
    {
        icon: MessageSquare,
        title: 'Comunicação central',
        description:
            'Avisos, comunicados e ocorrências sem depender de grupos de mensagem.',
    },
    {
        icon: ChartNoAxesCombined,
        title: 'Gestão transparente',
        description:
            'Acompanhe rotinas e solicitações com mais clareza para toda a comunidade.',
    },
];

export default function Welcome() {
    return (
        <>
            <Head title="CoSphere" />

            <div className="dark flex h-svh flex-col overflow-hidden bg-cosphere-surface text-cosphere-navy">
                <header className="mx-auto flex w-full max-w-7xl shrink-0 items-center justify-end px-5 py-3 sm:px-8 lg:px-10">
                    <Button
                        asChild
                        size="lg"
                        className="rounded-xl bg-cosphere-orange px-7 text-white shadow-cosphere-soft hover:bg-cosphere-orange/90"
                    >
                        <Link href={login()}>Entrar</Link>
                    </Button>
                </header>

                <main className="mx-auto flex min-h-0 w-full max-w-7xl flex-1 flex-col justify-center px-5 pb-2 sm:px-8 lg:px-10">
                    <section className="grid items-center gap-6 md:grid-cols-[minmax(0,0.94fr)_minmax(16rem,0.68fr)] lg:gap-10 xl:gap-16">
                        <div className="max-w-2xl">
                            <Link
                                href={home()}
                                className="flex w-full justify-center"
                                aria-label="CoSphere — página inicial"
                            >
                                <CoSphereLogo
                                    size="lg"
                                    className="size-64 sm:size-72"
                                />
                            </Link>

                            <h1 className="mt-1 text-2xl leading-[1.08] font-semibold tracking-tight text-cosphere-navy sm:text-3xl lg:text-4xl xl:text-5xl dark:text-foreground">
                                Tudo o que seu condomínio precisa,
                                <span className="block text-cosphere-orange">
                                    em um só lugar.
                                </span>
                            </h1>

                            <p className="mt-4 max-w-xl text-sm leading-relaxed text-cosphere-muted sm:text-base">
                                O CoSphere centraliza a gestão e a comunicação
                                do condomínio: unidades, moradores, documentos,
                                solicitações e avisos em um ambiente seguro e
                                organizado.
                            </p>

                            <div className="mt-5 flex flex-wrap items-center gap-x-5 gap-y-3">
                                <span className="inline-flex items-center gap-2 text-sm font-medium text-cosphere-muted">
                                    <ShieldCheck
                                        className="size-5 text-emerald-600"
                                        aria-hidden="true"
                                    />
                                    Ambiente de acesso restrito
                                </span>
                            </div>
                        </div>

                        <div className="relative mx-auto hidden h-[26rem] w-full max-w-sm md:block lg:h-[29rem] lg:max-w-[24rem] xl:h-[31rem] xl:max-w-[27rem]">
                            <div className="h-full overflow-hidden rounded-3xl border border-cosphere-line shadow-cosphere-panel">
                                <img
                                    src={condominioNoite}
                                    alt="Fachada iluminada de um condomínio residencial moderno ao anoitecer"
                                    className="size-full object-cover"
                                />
                            </div>
                            <div className="absolute inset-x-5 bottom-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/35 bg-card/92 px-4 py-3 shadow-cosphere-soft backdrop-blur sm:inset-x-8 sm:bottom-7 sm:px-5">
                                <span className="inline-flex items-center gap-2 text-sm font-semibold text-cosphere-navy dark:text-foreground">
                                    <ShieldCheck
                                        className="size-4 text-emerald-600"
                                        aria-hidden="true"
                                    />
                                    Gestão conectada e segura
                                </span>
                                <span className="text-xs font-medium text-cosphere-muted">
                                    Moradores · unidades · responsáveis
                                </span>
                            </div>
                        </div>
                    </section>

                    <section className="mt-5 hidden gap-3 lg:grid lg:grid-cols-3">
                        {features.map((feature) => (
                            <WelcomeFeatureCard
                                key={feature.title}
                                {...feature}
                            />
                        ))}
                    </section>
                </main>

                <footer className="mx-auto hidden w-full max-w-7xl shrink-0 px-5 pb-3 text-xs sm:block sm:px-8 lg:px-10">
                    <div className="flex items-center justify-between gap-4 border-t border-cosphere-line pt-2 text-cosphere-muted">
                        <p>
                            © {new Date().getFullYear()} CoSphere · Gestão
                            Condominial
                        </p>
                        <p>
                            Acesso restrito a administradores, moradores e
                            porteiros autorizados.
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
