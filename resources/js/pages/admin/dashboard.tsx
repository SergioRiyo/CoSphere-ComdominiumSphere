import { Head, usePage } from '@inertiajs/react';
import { dashboard } from '@/routes/admin';

export default function AdminDashboard() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Dashboard administrativo" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <p className="text-sm font-medium text-muted-foreground">Administrador</p>
                    <h1 className="mt-2 text-2xl font-semibold">Olá, {auth.user.name}</h1>
                    <p className="mt-3 text-muted-foreground">
                        Esta é a área administrativa do CoSphere.
                    </p>
                </div>
            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
