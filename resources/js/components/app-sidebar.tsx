import { Link, usePage } from '@inertiajs/react';
import {
    ClipboardCheck,
    ContactRound,
    History,
    LayoutGrid,
    Users,
    UsersRound,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as usersIndex } from '@/routes/admin/users';
import { dashboard as moradorDashboard } from '@/routes/morador';
import { index as visitorsIndex } from '@/routes/morador/visitors';
import { dashboard as portariaDashboard } from '@/routes/portaria';
import { index as visitorAccessHistoryIndex } from '@/routes/portaria/visitor-access-history';
import { index as visitorAccessesIndex } from '@/routes/portaria/visitor-accesses';
import { validation } from '@/routes/portaria/visitor-authorizations';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth } = usePage().props;
    const dashboardHref = {
        admin: adminDashboard(),
        morador: moradorDashboard(),
        porteiro: portariaDashboard(),
    }[auth.user.role];
    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardHref,
            icon: LayoutGrid,
        },
        ...(auth.user.role === 'admin'
            ? [
                  {
                      title: 'Gestão de usuários',
                      href: usersIndex(),
                      icon: Users,
                  },
              ]
            : []),
        ...(auth.user.role === 'morador'
            ? [
                  {
                      title: 'Visitantes',
                      href: visitorsIndex(),
                      icon: ContactRound,
                  },
              ]
            : []),
        ...(auth.user.role === 'porteiro'
            ? [
                  {
                      title: 'Validar visitante',
                      href: validation(),
                      icon: ClipboardCheck,
                  },
                  {
                      title: 'Visitantes presentes',
                      href: visitorAccessesIndex(),
                      icon: UsersRound,
                  },
                  {
                      title: 'Histórico de acessos',
                      href: visitorAccessHistoryIndex(),
                      icon: History,
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardHref} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
