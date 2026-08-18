import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Building2,
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

            <div className="min-h-svh overflow-x-hidden bg-cosphere-surface text-cosphere-navy">
                <header className="mx-auto flex w-full max-w-7xl items-center justify-between px-5 py-4 sm:px-8 lg:px-10 lg:py-5">
                    <Link href={home()} aria-label="CoSphere — página inicial">
                        <CoSphereLogo size="md" />
                    </Link>

                    <Button
                        asChild
                        size="lg"
                        className="rounded-xl bg-cosphere-orange px-7 text-white shadow-cosphere-soft hover:bg-cosphere-orange/90"
                    >
                        <Link href={login()}>Entrar</Link>
                    </Button>
                </header>

                <main className="mx-auto w-full max-w-7xl px-5 pt-3 pb-3 sm:px-8 sm:pt-6 lg:px-10 lg:pt-6 lg:pb-6">
                    <section className="grid items-center gap-12 lg:grid-cols-[minmax(0,0.94fr)_minmax(22rem,0.68fr)] lg:gap-16 xl:gap-24">
                        <div className="max-w-2xl">
                            <span className="inline-flex items-center gap-2 rounded-full border border-cosphere-line bg-white px-3.5 py-2 text-xs font-semibold tracking-[0.16em] text-cosphere-muted uppercase shadow-sm">
                                <Building2
                                    className="size-4 text-cosphere-orange"
                                    aria-hidden="true"
                                />
                                Plataforma de gestão condominial
                            </span>

                            <h1 className="mt-7 text-3xl leading-[1.08] font-semibold tracking-tight sm:text-4xl lg:text-5xl xl:text-[3.75rem]">
                                Tudo o que seu condomínio precisa,
                                <span className="block text-cosphere-orange">
                                    em um só lugar.
                                </span>
                            </h1>

                            <p className="mt-7 max-w-xl text-base leading-relaxed text-cosphere-muted sm:text-lg">
                                O CoSphere centraliza a gestão e a comunicação
                                do condomínio: unidades, moradores, documentos,
                                solicitações e avisos em um ambiente seguro e
                                organizado.
                            </p>

                            <div className="mt-9 flex flex-wrap items-center gap-x-5 gap-y-4">
                                <Button
                                    asChild
                                    size="lg"
                                    className="h-12 rounded-xl bg-cosphere-orange px-7 text-base text-white shadow-cosphere-soft hover:bg-cosphere-orange/90"
                                >
                                    <Link href={login()}>
                                        Entrar
                                        <ArrowRight
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </Button>
                                <span className="inline-flex items-center gap-2 text-sm font-medium text-cosphere-muted">
                                    <ShieldCheck
                                        className="size-5 text-emerald-600"
                                        aria-hidden="true"
                                    />
                                    Ambiente de acesso restrito
                                </span>
                            </div>
                        </div>

                        <div className="relative mx-auto w-full max-w-xl lg:max-w-[28rem] xl:max-w-[32rem]">
                            <div className="overflow-hidden rounded-3xl border border-cosphere-line shadow-cosphere-panel">
                                <img
                                    src={condominioNoite}
                                    alt="Fachada iluminada de um condomínio residencial moderno ao anoitecer"
                                    className="aspect-[4/3] w-full object-cover lg:aspect-square"
                                />
                            </div>
                            <div className="absolute inset-x-5 bottom-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/35 bg-white/92 px-4 py-3 shadow-cosphere-soft backdrop-blur sm:inset-x-8 sm:bottom-7 sm:px-5">
                                <span className="inline-flex items-center gap-2 text-sm font-semibold text-cosphere-navy">
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

                    <section className="mt-16 grid gap-4 sm:grid-cols-3 lg:mt-24">
                        {features.map((feature) => (
                            <WelcomeFeatureCard
                                key={feature.title}
                                {...feature}
                            />
                        ))}
                    </section>
                </main>

                <footer className="mx-auto w-full max-w-7xl px-5 pb-4 sm:px-8 lg:px-10 lg:pb-6">
                    <div className="flex flex-col gap-2 border-t border-cosphere-line pt-3 text-sm text-cosphere-muted sm:flex-row sm:items-center sm:justify-between">
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
