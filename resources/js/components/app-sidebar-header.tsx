import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { index as notificationsIndex } from '@/routes/notifications';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { auth, unreadNotifications } = usePage().props;

    return (
        <header className="flex h-16 shrink-0 items-center gap-3 border-b border-border/70 bg-background/80 px-4 backdrop-blur-xl transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 sm:px-6">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            {auth.user && auth.user.role !== 'porteiro' && (
                <Button
                    asChild
                    variant="ghost"
                    size="icon"
                    className="relative ml-auto"
                >
                    <Link
                        href={notificationsIndex()}
                        aria-label={
                            unreadNotifications
                                ? `Notificações, ${unreadNotifications} não lidas`
                                : 'Notificações'
                        }
                    >
                        <Bell className="size-5" aria-hidden="true" />
                        {!!unreadNotifications && (
                            <span className="absolute top-0 right-0 flex min-w-4 items-center justify-center rounded-full bg-cosphere-orange px-1 text-[10px] leading-4 text-white">
                                {unreadNotifications > 99
                                    ? '99+'
                                    : unreadNotifications}
                            </span>
                        )}
                    </Link>
                </Button>
            )}
        </header>
    );
}
