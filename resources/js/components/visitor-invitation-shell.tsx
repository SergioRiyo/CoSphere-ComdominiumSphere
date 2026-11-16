import type { ReactNode } from 'react';
import CoSphereLogo from '@/components/cosphere-logo';
import ThemeToggle from '@/components/theme-toggle';

export default function VisitorInvitationShell({
    children,
}: {
    children: ReactNode;
}) {
    return (
        <main className="min-h-svh bg-background text-foreground">
            <div className="mx-auto flex min-h-svh w-full max-w-5xl flex-col px-4 py-5 sm:px-8 sm:py-7">
                <header className="flex items-center justify-between gap-4">
                    <CoSphereLogo size="sm" withTagline className="size-24" />
                    <ThemeToggle />
                </header>
                <div className="flex flex-1 items-center justify-center py-6 sm:py-10">
                    {children}
                </div>
            </div>
        </main>
    );
}
