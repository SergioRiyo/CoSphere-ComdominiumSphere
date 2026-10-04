import { Form, Head, Link, router } from '@inertiajs/react';
import { Package } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { OrderPickupButton } from '@/components/order-pickup-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes/portaria';
import { index as historyIndex } from '@/routes/portaria/order-history';
import { index, pickup, receive, store } from '@/routes/portaria/orders';

type Unit = { id: number; block: string | null; number: string };
type ExpectedOrder = {
    id: number;
    description: string | null;
    carrier: string | null;
    sender: string | null;
    tracking_code: string | null;
    unit: Unit;
    resident_name: string | null;
    can_receive: boolean;
};
type Props = {
    timezone: string;
    receivedOrders: {
        data: {
            id: number;
            description: string | null;
            tracking_code: string | null;
            unit: Unit;
            resident_name: string | null;
            received_at: string | null;
            can_pickup: boolean;
        }[];
        current_page: number;
        last_page: number;
        total: number;
    };
    orders: {
        data: ExpectedOrder[];
        current_page: number;
        last_page: number;
        total: number;
    };
    unitOptions: Unit[];
    residentOptions: { id: number; name: string }[];
    filters: { unit_id: number | null; search: string };
    errors?: Record<string, string>;
};

const unitLabel = (unit: Unit) =>
    `${unit.block ? `Bloco ${unit.block} · ` : ''}Unidade ${unit.number}`;
const selectClass =
    'h-10 w-full rounded-md border border-input bg-background px-3 text-sm';

export default function PortariaOrdersPage({
    receivedOrders,
    timezone,
    orders,
    unitOptions,
    residentOptions,
    filters,
    errors = {},
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [selected, setSelected] = useState<ExpectedOrder | null>(null);
    const [showUnexpected, setShowUnexpected] = useState(false);
    const [busy, setBusy] = useState(false);
    const [requestError, setRequestError] = useState<string | null>(null);
    const handleFailure = () => {
        setRequestError(
            'Não foi possível concluir a solicitação. Verifique sua conexão e tente novamente.',
        );

        return false;
    };
    const visit = (unitId: string, text: string) => {
        setShowUnexpected(false);
        setRequestError(null);
        router.get(
            index.url(),
            { unit_id: unitId || undefined, search: text || undefined },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onHttpException: handleFailure,
                onNetworkError: handleFailure,
            },
        );
    };

    return (
        <>
            <Head title="Recebimento de encomendas" />
            <div className="px-4 pt-4 sm:px-6">
                <Button variant="outline" asChild>
                    <Link href={historyIndex()}>
                        Consultar histórico de encomendas
                    </Link>
                </Button>
            </div>
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-2">
                    <h1 className="flex items-center gap-2 text-3xl font-semibold tracking-tight">
                        <Package aria-hidden="true" />
                        Recebimento de encomendas
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Localize a previsão antes de registrar uma encomenda não
                        prevista.
                    </p>
                </header>
                {requestError && (
                    <p role="alert" className="text-sm text-destructive">
                        {requestError}
                    </p>
                )}
                <Card>
                    <CardHeader>
                        <CardTitle>Buscar encomenda prevista</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="grid gap-4 sm:grid-cols-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                visit(String(filters.unit_id ?? ''), search);
                            }}
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="unit">
                                    Unidade destinatária
                                </Label>
                                <select
                                    id="unit"
                                    className={selectClass}
                                    value={filters.unit_id ?? ''}
                                    disabled={busy}
                                    onChange={(event) =>
                                        visit(event.target.value, search)
                                    }
                                >
                                    <option value="">Todas as unidades</option>
                                    {unitOptions.map((unit) => (
                                        <option key={unit.id} value={unit.id}>
                                            {unitLabel(unit)}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.unit_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="search">
                                    Código de rastreio ou remetente
                                </Label>
                                <Input
                                    id="search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    maxLength={255}
                                />
                                <InputError message={errors.search} />
                            </div>
                            <div className="flex gap-2">
                                <Button disabled={busy}>Buscar</Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={busy}
                                    onClick={() => {
                                        setSearch('');
                                        visit('', '');
                                    }}
                                >
                                    Limpar filtros
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <section
                    className="flex flex-col gap-4"
                    aria-labelledby="pickup-heading"
                >
                    <h2 id="pickup-heading" className="text-xl font-semibold">
                        Aguardando retirada ({receivedOrders.total})
                    </h2>
                    {receivedOrders.data.length === 0 && (
                        <p className="rounded-lg border p-6 text-sm text-muted-foreground">
                            Nenhuma encomenda aguardando retirada para esta
                            busca.
                        </p>
                    )}
                    <ul className="grid gap-4 lg:grid-cols-2">
                        {receivedOrders.data.map((order) => (
                            <li key={order.id}>
                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            Encomenda #{order.id}
                                        </CardTitle>
                                        <p className="text-sm">
                                            {unitLabel(order.unit)} ·{' '}
                                            {order.resident_name}
                                        </p>
                                    </CardHeader>
                                    <CardContent className="flex flex-col gap-3 text-sm">
                                        <p className="break-words whitespace-pre-wrap">
                                            {order.description ||
                                                'Sem descrição'}
                                        </p>
                                        <p>
                                            Rastreio:{' '}
                                            {order.tracking_code ||
                                                'Não informado'}
                                        </p>
                                        <p>
                                            Recebida em:{' '}
                                            {order.received_at
                                                ? new Intl.DateTimeFormat(
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
                                                  )
                                                : 'Data não informada'}
                                        </p>
                                        {order.can_pickup ? (
                                            <OrderPickupButton
                                                action={pickup.url(order.id)}
                                                orderId={order.id}
                                                label="Registrar retirada"
                                            />
                                        ) : (
                                            <p className="text-destructive">
                                                Confirmação indisponível:
                                                verifique o vínculo do
                                                destinatário com a
                                                administração.
                                            </p>
                                        )}
                                    </CardContent>
                                </Card>
                            </li>
                        ))}
                    </ul>
                    {receivedOrders.last_page > 1 && (
                        <nav
                            aria-label="Paginação de encomendas aguardando retirada"
                            className="flex items-center justify-between gap-3"
                        >
                            <p className="text-sm">
                                Página {receivedOrders.current_page} de{' '}
                                {receivedOrders.last_page}
                            </p>
                            <div className="flex gap-2">
                                {receivedOrders.current_page > 1 && (
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={index({
                                                query: {
                                                    ...filters,
                                                    page: orders.current_page,
                                                    received_page:
                                                        receivedOrders.current_page -
                                                        1,
                                                },
                                            })}
                                            preserveScroll
                                            onHttpException={handleFailure}
                                            onNetworkError={handleFailure}
                                        >
                                            Anterior
                                        </Link>
                                    </Button>
                                )}
                                {receivedOrders.current_page <
                                    receivedOrders.last_page && (
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={index({
                                                query: {
                                                    ...filters,
                                                    page: orders.current_page,
                                                    received_page:
                                                        receivedOrders.current_page +
                                                        1,
                                                },
                                            })}
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

                <section
                    className="flex flex-col gap-4"
                    aria-labelledby="expected-heading"
                    aria-busy={busy}
                >
                    <h2 id="expected-heading" className="text-xl font-semibold">
                        Aguardando entrega ({orders.total})
                    </h2>
                    {orders.data.length === 0 ? (
                        <p className="rounded-lg border p-6 text-sm text-muted-foreground">
                            Nenhuma encomenda prevista encontrada para esta
                            busca.
                        </p>
                    ) : (
                        <ul className="grid gap-4 lg:grid-cols-2">
                            {orders.data.map((order) => (
                                <li key={order.id}>
                                    <Card className="h-full">
                                        <CardHeader>
                                            <CardTitle>
                                                Encomenda #{order.id}
                                            </CardTitle>
                                            <p className="text-sm">
                                                {unitLabel(order.unit)} ·{' '}
                                                {order.resident_name ??
                                                    'Destinatário indisponível'}
                                            </p>
                                        </CardHeader>
                                        <CardContent className="flex flex-col gap-3 text-sm">
                                            <p className="break-words whitespace-pre-wrap">
                                                {order.description ||
                                                    'Sem descrição'}
                                            </p>
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
                                                        Rastreio
                                                    </dt>
                                                    <dd>
                                                        {order.tracking_code ||
                                                            'Não informado'}
                                                    </dd>
                                                </div>
                                            </dl>
                                            {!order.can_receive && (
                                                <p className="text-destructive">
                                                    O destinatário está
                                                    indisponível ou não pertence
                                                    mais à unidade original.
                                                    Confirme o destino antes de
                                                    registrar um novo
                                                    recebimento.
                                                </p>
                                            )}
                                            <Button
                                                className="self-start"
                                                disabled={
                                                    busy || !order.can_receive
                                                }
                                                onClick={() => {
                                                    setRequestError(null);
                                                    setSelected(order);
                                                }}
                                            >
                                                Receber encomenda #{order.id}
                                            </Button>
                                        </CardContent>
                                    </Card>
                                </li>
                            ))}
                        </ul>
                    )}
                    {orders.last_page > 1 && (
                        <nav
                            className="flex flex-wrap items-center justify-between gap-3"
                            aria-label="Paginação de encomendas previstas"
                        >
                            <p className="text-sm">
                                Página {orders.current_page} de{' '}
                                {orders.last_page}
                            </p>
                            <div className="flex gap-2">
                                {[
                                    [-1, 'Anterior'],
                                    [1, 'Próxima'],
                                ].map(([offset, label]) => {
                                    const page =
                                        orders.current_page + Number(offset);

                                    return page >= 1 &&
                                        page <= orders.last_page ? (
                                        <Button
                                            key={label}
                                            variant="outline"
                                            asChild
                                        >
                                            <Link
                                                href={index({
                                                    query: {
                                                        ...filters,
                                                        page,
                                                        received_page:
                                                            receivedOrders.current_page,
                                                    },
                                                })}
                                                preserveScroll
                                                onHttpException={handleFailure}
                                                onNetworkError={handleFailure}
                                            >
                                                {label}
                                            </Link>
                                        </Button>
                                    ) : null;
                                })}
                            </div>
                        </nav>
                    )}
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>Encomenda não prevista</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <p className="text-sm text-muted-foreground">
                            Se a encomenda não corresponde aos registros acima,
                            selecione a unidade e cadastre o recebimento abaixo.
                        </p>
                        {!filters.unit_id ? (
                            <p className="text-sm">
                                Selecione uma unidade na busca para escolher o
                                destinatário.
                            </p>
                        ) : !showUnexpected ? (
                            <Button
                                variant="outline"
                                className="self-start"
                                disabled={busy}
                                onClick={() => setShowUnexpected(true)}
                            >
                                Não encontrei a encomenda: cadastrar recebimento
                            </Button>
                        ) : (
                            <Form
                                key={filters.unit_id}
                                {...store.form()}
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                onBefore={() => setRequestError(null)}
                                onSuccess={() => setShowUnexpected(false)}
                                onHttpException={handleFailure}
                                onNetworkError={handleFailure}
                            >
                                {({ errors: formErrors, processing }) => (
                                    <div className="flex flex-col gap-4">
                                        <input
                                            type="hidden"
                                            name="unit_id"
                                            value={filters.unit_id ?? ''}
                                        />
                                        <p className="text-sm font-medium">
                                            {unitOptions.find(
                                                (unit) =>
                                                    unit.id === filters.unit_id,
                                            ) &&
                                                unitLabel(
                                                    unitOptions.find(
                                                        (unit) =>
                                                            unit.id ===
                                                            filters.unit_id,
                                                    )!,
                                                )}
                                        </p>
                                        <Label htmlFor="resident">
                                            Morador destinatário
                                        </Label>
                                        <select
                                            id="resident"
                                            name="resident_id"
                                            required
                                            className={selectClass}
                                            defaultValue=""
                                            disabled={processing}
                                            aria-invalid={Boolean(
                                                formErrors.resident_id,
                                            )}
                                            aria-describedby="resident-error"
                                        >
                                            <option value="" disabled>
                                                Selecione o morador
                                            </option>
                                            {residentOptions.map((resident) => (
                                                <option
                                                    key={resident.id}
                                                    value={resident.id}
                                                >
                                                    {resident.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            id="resident-error"
                                            message={formErrors.resident_id}
                                        />
                                        {residentOptions.length === 0 && (
                                            <p role="alert" className="text-sm">
                                                Não há moradores ativos
                                                vinculados à unidade
                                                selecionada.
                                            </p>
                                        )}
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
                                                            formErrors[name],
                                                        )}
                                                        aria-describedby={`${name}-error`}
                                                    />
                                                    <InputError
                                                        id={`${name}-error`}
                                                        message={
                                                            formErrors[name]
                                                        }
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
                                                aria-invalid={Boolean(
                                                    formErrors.description,
                                                )}
                                                aria-describedby="description-error"
                                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                                            />
                                            <InputError
                                                id="description-error"
                                                message={formErrors.description}
                                            />
                                        </div>
                                        {Object.entries(formErrors)
                                            .filter(
                                                ([field]) =>
                                                    ![
                                                        'resident_id',
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
                                            O recebimento será registrado agora,
                                            em seu nome, e o destinatário será
                                            notificado.
                                        </p>
                                        <Button
                                            type="submit"
                                            className="self-start"
                                            disabled={
                                                processing ||
                                                residentOptions.length === 0
                                            }
                                        >
                                            {processing
                                                ? 'Registrando...'
                                                : 'Cadastrar e receber'}
                                        </Button>
                                    </div>
                                )}
                            </Form>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open && !busy) {
                        setSelected(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirmar recebimento</DialogTitle>
                        <DialogDescription>
                            Confirme que a encomenda entregue corresponde a esta
                            previsão. O destinatário será notificado.
                        </DialogDescription>
                    </DialogHeader>
                    {selected && (
                        <>
                            <p className="text-sm">
                                Encomenda #{selected.id} ·{' '}
                                {unitLabel(selected.unit)} ·{' '}
                                {selected.resident_name}
                            </p>
                            <p className="text-sm break-words whitespace-pre-wrap">
                                {selected.description || 'Sem descrição'}
                            </p>
                            {requestError && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {requestError}
                                </p>
                            )}
                            <Form
                                key={selected.id}
                                {...receive.form(selected.id)}
                                options={{ preserveScroll: true }}
                                onStart={() => setBusy(true)}
                                onFinish={() => setBusy(false)}
                                onSuccess={() => setSelected(null)}
                                onHttpException={handleFailure}
                                onNetworkError={handleFailure}
                            >
                                {({ errors: formErrors, processing }) => (
                                    <div className="flex flex-col gap-4">
                                        {Object.entries(formErrors).map(
                                            ([field, message]) => (
                                                <InputError
                                                    key={field}
                                                    message={message}
                                                    role="alert"
                                                />
                                            ),
                                        )}
                                        <div className="flex gap-2">
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing
                                                    ? 'Registrando...'
                                                    : 'Confirmar recebimento'}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled={processing}
                                                onClick={() =>
                                                    setSelected(null)
                                                }
                                            >
                                                Cancelar
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </Form>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

PortariaOrdersPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Receber encomendas', href: index() },
    ],
};
