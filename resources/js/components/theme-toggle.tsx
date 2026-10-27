import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

type ThemeToggleProps = {
    className?: string;
    inverted?: boolean;
};

export default function ThemeToggle({
    className,
    inverted = false,
}: ThemeToggleProps) {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
            className={cn(
                'rounded-xl border border-border/70 bg-background/75 shadow-sm backdrop-blur hover:bg-accent',
                inverted &&
                    'border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white',
                className,
            )}
            aria-label={isDark ? 'Ativar modo claro' : 'Ativar modo escuro'}
            title={isDark ? 'Ativar modo claro' : 'Ativar modo escuro'}
        >
            {isDark ? (
                <Sun className="size-4.5" aria-hidden="true" />
            ) : (
                <Moon className="size-4.5" aria-hidden="true" />
            )}
        </Button>
    );
}
