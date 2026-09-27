import { Form, Head, Link } from '@inertiajs/react';
import { Package } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { OrderHistoryFilterForm } from '@/components/order-history-filters';
import { OrderPickupButton } from '@/components/order-pickup-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes/morador';
import { index, pickup, show, store } from '@/routes/morador/orders';
import type { OrderHistoryFilters } from '@/types/order-history';

type Order = {
    id: number;
    description: string | null;
    carrier: string | null;
    sender: string | null;
    tracking_code: string | null;
    status: string;
    status_label: string;
    received_at: string | null;
    picked_up_at: string | null;
    pickup_confirmed_by: string | null;
    can_pickup: boolean;
    created_at: string | null;
    available_for_pickup: boolean;
    received_by: string | null;
};

type OrdersPageProps = {
    filters: OrderHistoryFilters;
    statusOptions: { value: string; label: string }[];
    timezone: string;
    unit: { id: number; block: string | null; number: string } | null;
    orders: {
        data: Order[];
        current_page: number;
        last_page: number;
        total: number;
    };
};

export default function OrdersPage({
    unit,
    orders,
    timezone,
    filters,
    statusOptions,
}: OrdersPageProps) {
    const [requestError, setRequestError] = useState<string | null>(null);
    const handleFailure = () => {
        setRequestError(
            'Não foi possível concluir a solicitação. Tente novamente.',
        );

        return false;
    };

    return (
        <>
            <Head title="Encomendas" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-2">
                    <h1 className="flex items-center gap-2 text-3xl font-semibold tracking-tight">
                        <Package aria-hidden="true" />
                        Encomendas
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Informe uma encomenda aguardada para facilitar sua
                        identificação na portaria.
                    </p>
                    {unit && (
                        <p className="text-sm font-medium">
                            {unit.block ? `Bloco ${unit.block} · ` : ''}Unidade{' '}
                            {unit.number}
                        </p>
                    )}
                </header>

                {requestError && (
                    <p role="alert" className="text-sm text-destructive">
                        {requestError}
                    </p>
                )}

                {!unit ? (
                    <p role="alert" className="rounded-lg border p-4">
                        Você precisa estar vinculado a uma unidade para
                        cadastrar encomendas. Entre em contato com a
                        administração.
                    </p>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle>Cadastrar encomenda prevista</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...store.form()}
                                resetOnSuccess
                                options={{ preserveScroll: true }}
                                onBefore={() => setRequestError(null)}
                                onHttpException={handleFailure}
                                onNetworkError={handleFailure}
                            >
                                {({ errors, processing }) => (
                                    <div className="flex flex-col gap-4">
                                        <div className="grid gap-4 sm:grid-cols-3">
                                            {(
                                                [
                                                    [
                                                        'carrier',
                                                        'Transportadora',
                                                    ],
                                                    ['sender', 'Remetente'],
                                                    [
                                                        'tracking_code',
                                                        'Código de rastreio',
                                                    ],
                                                ] as const
                                            ).map(([name, label]) => (
                                                <div
                                                    key={name}
                                                    className="grid gap-2"
                                                >
                                                    <Label htmlFor={name}>
                                                        {label} (opcional)
                                                    </Label>
                                                    <Input
                                                        id={name}
                                                        name={name}
                                                        maxLength={255}
                                                        aria-invalid={Boolean(
                                                            errors[name],
                                                        )}
                                                        aria-describedby={`${name}-error`}
                                                    />
                                                    <InputError
                                                        id={`${name}-error`}
                                                        message={errors[name]}
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="description">
                                                Identificação/descrição
                                                (obrigatória)
                                            </Label>
                                            <textarea
                                                id="description"
                                                name="description"
                                                required
                                                maxLength={5000}
                                                rows={3}
                                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                placeholder="Ex.: caixa de livros da loja..."
                                                aria-invalid={Boolean(
                                                    errors.description,
                                                )}
                                                aria-describedby="description-error"
                                            />
                                            <InputError
                                                id="description-error"
                                                message={errors.description}
                                            />
                                        </div>
                                        {Object.entries(errors)
                                            .filter(
                                                ([field]) =>
                                                    ![
                                                        'description',
                                                        'carrier',
                                                        'sender',
                                                        'tracking_code',
                                                    ].includes(field),
                                            )
                                            .map(([field, message]) => (
                                                <InputError
                                                    key={field}
                                                    message={message}
                                                    role="alert"
                                                />
                                            ))}
                                        <p className="text-sm text-muted-foreground">
                                            A encomenda será vinculada à sua
                                            unidade com status Aguardando
                                            entrega.
                                        </p>
                                        <Button
                                            type="submit"
                                            className="self-start"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Cadastrando...'
                                                : 'Cadastrar encomenda'}
                                        </Button>
                                    </div>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}

                <section
                    className="flex flex-col gap-4"
                    aria-labelledby="orders-heading"
                >
                    <h2 id="orders-heading" className="text-xl font-semibold">
                        Encomendas da unidade
                    </h2>
                    <OrderHistoryFilterForm
                        action={index.url()}
                        filters={filters}
                        statusOptions={statusOptions}
                    />
                    {orders.data.length === 0 ? (
                        <p className="rounded-lg border p-6 text-sm text-muted-foreground">
                            {Object.values(filters).some(Boolean)
                                ? 'Nenhuma encomenda encontrada para os filtros informados.'
                                : 'Nenhuma encomenda cadastrada nesta página.'}
                        </p>
                    ) : (
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
                                            <CardTitle>
                                                Encomenda #{order.id}
                                            </CardTitle>
                                            <Badge
                                                variant={
                                                    order.available_for_pickup
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {order.status_label}
                                            </Badge>
                                            {order.available_for_pickup && (
                                                <p className="text-sm font-semibold text-primary">
                                                    Disponível para retirada
                                                </p>
                                            )}
                                        </CardHeader>
                                        <CardContent className="flex flex-col gap-3 text-sm">
                                            <Button
                                                variant="outline"
                                                className="self-start"
                                                asChild
                                            >
                                                <Link href={show(order.id)}>
                                                    Ver detalhes
                                                </Link>
                                            </Button>
                                            {order.created_at && (
                                                <p>
                                                    Cadastrada em{' '}
                                                    {new Intl.DateTimeFormat(
                                                        'pt-BR',
                                                        {
                                                            dateStyle: 'short',
                                                            timeStyle: 'short',
                                                            timeZone: timezone,
                                                        },
                                                    ).format(
                                                        new Date(
                                                            order.created_at,
                                                        ),
                                                    )}
                                                </p>
                                            )}
                                            {order.received_by && (
                                                <p>
                                                    Recebida por:{' '}
                                                    {order.received_by}
                                                </p>
                                            )}
                                            <p className="break-words whitespace-pre-wrap">
                                                {order.description ||
                                                    'Sem descrição'}
                                            </p>
                                            {order.received_at && (
                                                <p>
                                                    Recebida em{' '}
                                                    {new Intl.DateTimeFormat(
                                                        'pt-BR',
                                                        {
                                                            dateStyle: 'short',
                                                            timeStyle: 'short',
                                                            timeZone: timezone,
                                                        },
                                                    ).format(
                                                        new Date(
                                                            order.received_at,
                                                        ),
                                                    )}
                                                </p>
                                            )}
                                            {order.picked_up_at && (
                                                <div className="flex flex-col gap-1">
                                                    <p>
                                                        Retirada em{' '}
                                                        {new Intl.DateTimeFormat(
                                                            'pt-BR',
                                                            {
                                                                dateStyle:
                                                                    'short',
                                                                timeStyle:
                                                                    'short',
                                                                timeZone:
                                                                    timezone,
                                                            },
                                                        ).format(
                                                            new Date(
                                                                order.picked_up_at,
                                                            ),
                                                        )}
                                                    </p>
                                                    <p>
                                                        Confirmada por:{' '}
                                                        {order.pickup_confirmed_by ||
                                                            'Usuário indisponível'}
                                                    </p>
                                                </div>
                                            )}
                                            {order.can_pickup && (
                                                <OrderPickupButton
                                                    action={pickup.url(
                                                        order.id,
                                                    )}
                                                    orderId={order.id}
                                                    label="Confirmar retirada"
                                                />
                                            )}
                                            {order.status ===
                                                'received_at_gate' &&
                                                !order.can_pickup && (
                                                    <p className="text-destructive">
                                                        Confirmação
                                                        indisponível: verifique
                                                        o vínculo do
                                                        destinatário com a
                                                        administração.
                                                    </p>
                                                )}
                                            <dl className="grid gap-2 break-words">
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        Transportadora
                                                    </dt>
                                                    <dd>
                                                        {order.carrier ||
                                                            'Não informada'}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        Remetente
                                                    </dt>
                                                    <dd>
                                                        {order.sender ||
                                                            'Não informado'}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        Código de rastreio
                                                    </dt>
                                                    <dd>
                                                        {order.tracking_code ||
                                                            'Não informado'}
                                                    </dd>
                                                </div>
                                            </dl>
                                        </CardContent>
                                    </Card>
                                </li>
                            ))}
                        </ul>
                    )}
                    {orders.last_page > 1 && (
                        <nav
                            aria-label="Paginação de encomendas"
                            className="flex flex-wrap items-center justify-between gap-3"
                        >
                            <p className="text-sm text-muted-foreground">
                                Página {orders.current_page} de{' '}
                                {orders.last_page}
                            </p>
                            <div className="flex gap-2">
                                {orders.current_page > 1 && (
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={index({
                                                query: {
                                                    page:
                                                        orders.current_page - 1,
                                                    ...filters,
                                                },
                                            })}
                                            preserveState
                                            preserveScroll
                                            onHttpException={handleFailure}
                                            onNetworkError={handleFailure}
                                        >
                                            Anterior
                                        </Link>
                                    </Button>
                                )}
                                {orders.current_page < orders.last_page && (
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={index({
                                                query: {
                                                    page:
                                                        orders.current_page + 1,
                                                    ...filters,
                                                },
                                            })}
                                            preserveState
                                            preserveScroll
                                            onHttpException={handleFailure}
                                            onNetworkError={handleFailure}
                                        >
                                            Próxima
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </nav>
                    )}
                </section>
            </div>
        </>
    );
}

OrdersPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Encomendas', href: index() },
    ],
};
