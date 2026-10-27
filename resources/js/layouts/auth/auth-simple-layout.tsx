import { Link } from '@inertiajs/react';
import AuthLoginShowcase from '@/components/auth-login-showcase';
import CoSphereLogo from '@/components/cosphere-logo';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <main className="dark h-svh overflow-hidden bg-cosphere-deep lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(27rem,0.9fr)] lg:grid-rows-[minmax(0,1fr)] lg:p-3 xl:p-4">
            <div className="hidden min-h-0 overflow-hidden lg:block">
                <AuthLoginShowcase />
            </div>

            <section className="relative flex h-svh items-center bg-background px-5 py-3 sm:px-8 lg:h-auto lg:min-h-0 lg:rounded-[1.75rem] xl:px-12">
                <div className="mx-auto w-full max-w-sm">
                    <div className="flex flex-col gap-4">
                        <div className="flex flex-col items-center gap-2">
                            <Link href={home()} className="font-medium">
                                <CoSphereLogo size="md" withTagline />
                                <span className="sr-only">{title}</span>
                            </Link>

                            <div className="space-y-2 text-center">
                                <h1 className="text-xl font-semibold tracking-tight">
                                    {title}
                                </h1>
                                <p className="text-center text-sm leading-relaxed text-muted-foreground">
                                    {description}
                                </p>
                            </div>
                        </div>
                        {children}
                    </div>
                </div>
            </section>
        </main>
    );
}
