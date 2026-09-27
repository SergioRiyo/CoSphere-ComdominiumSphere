import { Head, Link } from '@inertiajs/react';
import { OrderHistoryDetails } from '@/components/order-history-details';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as residentIndex } from '@/routes/morador/orders';
import { index as portariaIndex } from '@/routes/portaria/order-history';
import type { OrderHistoryEntry } from '@/types/order-history';

export default function OrderDetailsPage({
    order,
    timezone,
    portaria,
}: {
    order: OrderHistoryEntry;
    timezone: string;
    portaria: boolean;
}) {
    return (
        <>
            <Head title={`Encomenda #${order.id}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <h1 className="text-3xl font-semibold">
                    Detalhes da encomenda #{order.id}
                </h1>
                <Button variant="outline" className="self-start" asChild>
                    <Link href={portaria ? portariaIndex() : residentIndex()}>
                        Voltar às encomendas
                    </Link>
                </Button>
                <Card>
                    <CardHeader>
                        <CardTitle>Histórico da encomenda</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <OrderHistoryDetails
                            order={order}
                            timezone={timezone}
                        />
                    </CardContent>
                </Card>
                <p className="text-sm text-muted-foreground">
                    A unidade histórica é a registrada na encomenda. “Retirada
                    confirmada por” identifica quem registrou a operação, não
                    necessariamente quem levou o pacote.
                </p>
            </div>
        </>
    );
}
