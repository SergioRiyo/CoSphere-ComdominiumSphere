import { Form, router } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { OrderHistoryFilters } from '@/types/order-history';

export function OrderHistoryFilterForm({
    action,
    filters,
    statusOptions,
    unitOptions,
}: {
    action: string;
    filters: OrderHistoryFilters;
    statusOptions: { value: string; label: string }[];
    unitOptions?: { id: number; block: string | null; number: string }[];
}) {
    const [error, setError] = useState<string | null>(null);
    const fail = () => {
        setError('Não foi possível carregar o histórico. Tente novamente.');

        return false;
    };

    return (
        <Form
            key={JSON.stringify(filters)}
            action={action}
            method="get"
            options={{ preserveScroll: true }}
            onBefore={() => setError(null)}
            onHttpException={fail}
            onNetworkError={fail}
        >
            {({ errors, processing }) => (
                <div className="flex flex-col gap-4 rounded-lg border p-4">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {unitOptions && (
                            <div className="grid gap-2">
                                <Label htmlFor="history-unit">Unidade</Label>
                                <select
                                    id="history-unit"
                                    name="unit_id"
                                    defaultValue={filters.unit_id ?? ''}
                                    className="h-10 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="">Todas</option>
                                    {unitOptions.map((unit) => (
                                        <option key={unit.id} value={unit.id}>
                                            {unit.block
                                                ? `Bloco ${unit.block} · `
                                                : ''}
                                            Unidade {unit.number}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.unit_id} />
                            </div>
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor="history-status">Status</Label>
                            <select
                                id="history-status"
                                name="status"
                                defaultValue={filters.status}
                                className="h-10 rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="">Todos</option>
                                {statusOptions.map((status) => (
                                    <option
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.status} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="history-from">Data inicial</Label>
                            <Input
                                id="history-from"
                                type="date"
                                name="date_from"
                                defaultValue={filters.date_from}
                            />
                            <InputError message={errors.date_from} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="history-to">Data final</Label>
                            <Input
                                id="history-to"
                                type="date"
                                name="date_to"
                                defaultValue={filters.date_to}
                            />
                            <InputError message={errors.date_to} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="history-search">
                                Rastreio, remetente ou descrição
                            </Label>
                            <Input
                                id="history-search"
                                name="search"
                                defaultValue={filters.search}
                                maxLength={255}
                            />
                            <InputError message={errors.search} />
                        </div>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        O período considera a data do status atual: cadastro
                        para aguardadas, recebimento para recebidas, retirada
                        para retiradas e última atualização para canceladas.
                    </p>
                    {error && (
                        <p role="alert" className="text-sm text-destructive">
                            {error}
                        </p>
                    )}
                    <div className="flex gap-2">
                        <Button disabled={processing}>Filtrar</Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={() =>
                                router.get(
                                    action,
                                    {},
                                    {
                                        preserveScroll: true,
                                        onHttpException: fail,
                                        onNetworkError: fail,
                                    },
                                )
                            }
                        >
                            Limpar filtros
                        </Button>
                    </div>
                </div>
            )}
        </Form>
    );
}
