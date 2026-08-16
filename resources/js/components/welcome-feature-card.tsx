import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

type WelcomeFeatureCardProps = {
    icon: LucideIcon;
    title: string;
    description: string;
    className?: string;
};

export default function WelcomeFeatureCard({
    icon: Icon,
    title,
    description,
    className,
}: WelcomeFeatureCardProps) {
    return (
        <article
            className={cn(
                'group rounded-2xl border border-cosphere-line bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-cosphere-blue/35 hover:shadow-cosphere-soft',
                className,
            )}
        >
            <span className="inline-flex size-11 items-center justify-center rounded-xl bg-cosphere-blue/10 text-cosphere-blue transition-colors group-hover:bg-cosphere-orange/10 group-hover:text-cosphere-orange">
                <Icon className="size-5" aria-hidden="true" />
            </span>
            <h2 className="mt-5 text-lg font-semibold tracking-tight text-cosphere-navy">
                {title}
            </h2>
            <p className="mt-2 text-sm leading-relaxed text-cosphere-muted">
                {description}
            </p>
        </article>
    );
}
