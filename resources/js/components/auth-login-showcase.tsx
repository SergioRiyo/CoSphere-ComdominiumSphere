import {
    BarChart3,
    FileText,
    KeyRound,
    ShieldCheck,
    Users,
} from 'lucide-react';
import condominioNoite from '../../assets/condominio-noite.jpg';

const highlights = [
    {
        icon: Users,
        title: 'Transparência',
        description: 'Comunicação clara para todos.',
    },
    {
        icon: BarChart3,
        title: 'Eficiência',
        description: 'Rotinas integradas e organizadas.',
    },
    {
        icon: KeyRound,
        title: 'Acesso',
        description: 'Entradas e autorizações controladas.',
    },
];

export default function AuthLoginShowcase() {
    return (
        <aside className="flex h-full min-h-0 flex-col overflow-hidden rounded-[1.75rem] border border-white/10 bg-cosphere-deep p-5 text-white xl:p-6">
            <div>
                <p className="text-xs font-semibold tracking-[0.28em] text-cosphere-blue uppercase">
                    Gestão inteligente
                </p>
                <p className="mt-1 text-xs tracking-[0.2em] text-white/45 uppercase">
                    Para condomínios modernos
                </p>
            </div>

            <div className="mt-5 max-w-md">
                <h2 className="text-2xl leading-tight font-semibold tracking-tight xl:text-3xl">
                    Tudo o que seu condomínio precisa,
                    <span className="block text-cosphere-orange">
                        em um só lugar.
                    </span>
                </h2>
                <div className="mt-4 flex items-center gap-3">
                    <span className="size-2 rounded-full bg-cosphere-orange" />
                    <span className="h-px w-20 bg-white/20" />
                </div>
                <p className="mt-4 max-w-sm text-sm leading-relaxed text-white/60">
                    Organização, transparência e eficiência para uma gestão
                    completa e conectada.
                </p>
            </div>

            <div className="relative mx-auto mt-5 h-80 w-full max-w-4xl shrink-0 overflow-hidden rounded-2xl border border-white/10 lg:h-96 xl:h-[26rem]">
                <img
                    src={condominioNoite}
                    alt="Condomínio residencial iluminado ao anoitecer"
                    className="size-full object-cover"
                />
                <div className="pointer-events-none absolute inset-0 bg-cosphere-deep/35" />
                <span className="absolute top-4 left-4 inline-flex items-center gap-2 rounded-full border border-white/15 bg-cosphere-deep/75 px-3 py-1.5 text-xs font-medium backdrop-blur-sm">
                    <ShieldCheck
                        className="size-3.5 text-emerald-400"
                        aria-hidden="true"
                    />
                    Ambiente seguro
                </span>
                <span className="absolute right-4 bottom-4 inline-flex items-center gap-2 rounded-full border border-white/15 bg-cosphere-deep/75 px-3 py-1.5 text-xs font-medium backdrop-blur-sm">
                    <FileText
                        className="size-3.5 text-cosphere-blue"
                        aria-hidden="true"
                    />
                    Documentos centralizados
                </span>
            </div>

            <ul className="mx-auto mt-3 grid w-full max-w-4xl gap-2 rounded-2xl border border-white/10 bg-white/5 p-2.5 xl:grid-cols-3">
                {highlights.map(({ icon: Icon, title, description }) => (
                    <li key={title} className="flex gap-2.5 xl:block">
                        <span className="inline-flex size-8 shrink-0 items-center justify-center rounded-lg border border-white/15 text-cosphere-blue xl:mb-3">
                            <Icon className="size-4" aria-hidden="true" />
                        </span>
                        <span>
                            <span className="block text-sm font-semibold">
                                {title}
                            </span>
                            <span className="mt-0.5 block text-xs leading-snug text-white/55">
                                {description}
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
        </aside>
    );
}
