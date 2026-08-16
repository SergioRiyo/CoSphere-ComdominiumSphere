import { Building2 } from 'lucide-react';
import { cn } from '@/lib/utils';

type Tone = 'dark' | 'light';

type CoSphereLogoProps = {
    className?: string;
    tone?: Tone;
    withTagline?: boolean;
    size?: 'sm' | 'md' | 'lg';
};

const sizes = {
    sm: {
        mark: 'size-9',
        name: 'text-xl',
        tagline: 'text-[0.55rem]',
    },
    md: {
        mark: 'size-11',
        name: 'text-2xl',
        tagline: 'text-[0.6rem]',
    },
    lg: {
        mark: 'size-14',
        name: 'text-3xl',
        tagline: 'text-[0.65rem]',
    },
} as const;

export default function CoSphereLogo({
    className,
    tone = 'dark',
    withTagline = false,
    size = 'md',
}: CoSphereLogoProps) {
    const logoSize = sizes[size];
    const isLight = tone === 'light';

    return (
        <span
            className={cn('inline-flex items-center gap-2.5', className)}
            aria-label="CoSphere — Gestão Condominial"
        >
            <span
                aria-hidden="true"
                className={cn(
                    'relative inline-flex shrink-0 items-center justify-center rounded-full text-cosphere-navy',
                    logoSize.mark,
                    isLight && 'text-white',
                )}
            >
                <span className="absolute inset-0 rounded-full border border-current opacity-45" />
                <span className="absolute inset-x-0 top-1/2 h-1/3 -translate-y-1/2 rotate-[-28deg] rounded-[50%] border border-current opacity-35" />
                <Building2 className="size-[52%]" strokeWidth={1.8} />
                <span className="absolute top-[9%] right-[4%] size-[16%] rounded-full bg-cosphere-orange" />
                <span className="absolute right-[3%] bottom-[13%] size-[11%] rounded-full bg-emerald-500" />
            </span>

            <span className="flex flex-col leading-none">
                <span
                    className={cn(
                        'font-semibold tracking-tight',
                        logoSize.name,
                        isLight ? 'text-white' : 'text-cosphere-navy',
                    )}
                >
                    <span className="text-cosphere-blue">Co</span>Sphere
                </span>
                {withTagline ? (
                    <span
                        className={cn(
                            'mt-1 font-medium tracking-[0.23em] uppercase',
                            logoSize.tagline,
                            isLight ? 'text-white/65' : 'text-cosphere-muted',
                        )}
                    >
                        Gestão condominial
                    </span>
                ) : null}
            </span>
        </span>
    );
}
