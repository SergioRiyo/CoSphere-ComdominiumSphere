import { Link } from '@inertiajs/react';
import { Palette, ShieldCheck, UserRound } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Meu perfil',
        href: edit(),
        icon: UserRound,
    },
    {
        title: 'Segurança',
        href: editSecurity(),
        icon: ShieldCheck,
    },
    {
        title: 'Aparência',
        href: editAppearance(),
        icon: Palette,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6">
            <Heading
                title="Configurações"
                description="Gerencie seus dados pessoais, segurança e preferências."
            />

            <div className="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-8">
                <aside className="w-full shrink-0 lg:w-52">
                    <nav
                        className="grid grid-cols-3 gap-1 rounded-xl border bg-card p-1.5 lg:grid-cols-1"
                        aria-label="Configurações"
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn(
                                    'h-auto min-h-10 w-full justify-center gap-2 px-2 lg:justify-start lg:px-3',
                                    {
                                        'bg-cosphere-blue/10 text-cosphere-blue dark:bg-primary/15 dark:text-foreground':
                                            isCurrentOrParentUrl(item.href),
                                    },
                                )}
                            >
                                <Link
                                    href={item.href}
                                    aria-current={
                                        isCurrentOrParentUrl(item.href)
                                            ? 'page'
                                            : undefined
                                    }
                                >
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <div className="min-w-0 flex-1">
                    <section className="space-y-12">{children}</section>
                </div>
            </div>
        </div>
    );
}
