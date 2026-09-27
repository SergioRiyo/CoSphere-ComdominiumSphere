import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { OrderHistoryDetails } from '@/components/order-history-details';
import { OrderHistoryFilterForm } from '@/components/order-history-filters';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes/portaria';
import { index } from '@/routes/portaria/order-history';
import { show } from '@/routes/portaria/orders';
import type {
    OrderHistoryEntry,
    OrderHistoryFilters,
    OrderHistoryPagination,
} from '@/types/order-history';

export default function OrderHistoryPage({
    orders,
    filters,
    statusOptions,
    unitOptions,
    timezone,
}: {
    orders: OrderHistoryPagination;
    filters: OrderHistoryFilters;
    statusOptions: { value: string; label: string }[];
    unitOptions: OrderHistoryEntry['unit'][];
    timezone: string;
}) {
    const [error, setError] = useState<string | null>(null);
    const fail = () => {
        setError('Não foi possível carregar a página. Tente novamente.');

        return false;
    };
    const filtered = Object.values(filters).some(Boolean);

    return (
        <>
            <Head title="Histórico de encomendas" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <h1 className="text-3xl font-semibold">
                    Histórico de encomendas
                </h1>
                <OrderHistoryFilterForm
                    action={index.url()}
                    filters={filters}
                    statusOptions={statusOptions}
                    unitOptions={unitOptions}
                />
                {error && (
                    <p role="alert" className="text-sm text-destructive">
                        {error}
                    </p>
                )}
                {orders.data.length === 0 && (
                    <p className="rounded-lg border p-6 text-sm text-muted-foreground">
                        {filtered
                            ? 'Nenhuma encomenda encontrada para os filtros informados.'
                            : 'Nenhuma encomenda cadastrada nesta página.'}
                    </p>
                )}
                <ul className="grid gap-4 lg:grid-cols-2">
                    {orders.data.map((order) => (
                        <li key={order.id}>
                            <Card
                                className={
                                    order.available_for_pickup
                                        ? 'h-full border-primary/40'
                                        : 'h-full'
                                }
                            >
                                <CardHeader>
                                    <CardTitle>Encomenda #{order.id}</CardTitle>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-4">
                                    <OrderHistoryDetails
                                        order={order}
                                        timezone={timezone}
                                    />
                                    <Button variant="outline" asChild>
                                        <Link href={show(order.id)}>
                                            Ver detalhes
                                        </Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        </li>
                    ))}
                </ul>
                {orders.last_page > 1 && (
                    <nav
                        aria-label="Paginação do histórico"
                        className="flex flex-wrap justify-between gap-3"
                    >
                        <p>
                            Página {orders.current_page} de {orders.last_page}
                        </p>
                        <div className="flex gap-2">
                            {[
                                [-1, 'Anterior'],
                                [1, 'Próxima'],
                            ].map(([offset, label]) => {
                                const page =
                                    orders.current_page + Number(offset);

                                return page > 0 && page <= orders.last_page ? (
                                    <Button
                                        key={label}
                                        asChild
                                        variant="outline"
                                    >
                                        <Link
                                            href={index({
                                                query: { ...filters, page },
                                            })}
                                            preserveScroll
                                            onHttpException={fail}
                                            onNetworkError={fail}
                                        >
                                            {label}
                                        </Link>
                                    </Button>
                                ) : null;
                            })}
                        </div>
                    </nav>
                )}
            </div>
        </>
    );
}

OrderHistoryPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Histórico de encomendas', href: index() },
    ],
};
