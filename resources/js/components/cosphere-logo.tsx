import { cn } from '@/lib/utils';

type Tone = 'dark' | 'light';

type CoSphereLogoProps = {
    className?: string;
    tone?: Tone;
    withTagline?: boolean;
    size?: 'sm' | 'md' | 'lg';
};

const sizes = {
    sm: 'size-20',
    md: 'size-28',
    lg: 'size-36',
} as const;

export default function CoSphereLogo({
    className,
    size = 'md',
}: CoSphereLogoProps) {
    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center justify-center',
                sizes[size],
                className,
            )}
            aria-label="CoSphere — Gestão Condominial"
        >
            <img
                src="/images/logo.png"
                alt="CoSphere — Gestão Condominial"
                className="size-full object-contain"
            />
        </span>
    );
}
