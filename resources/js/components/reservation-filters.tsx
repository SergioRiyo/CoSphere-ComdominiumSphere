import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as adminIndex } from '@/routes/admin/reservations';
import { index as residentIndex } from '@/routes/morador/reservations';
import type {
    ReservationFilters,
    ReservationStatus,
} from '@/types/reservation';

export default function ReservationFiltersForm({
    filters,
    statuses,
    admin,
    busy,
    onBusy,
}: {
    filters: ReservationFilters;
    statuses: { value: ReservationStatus; label: string }[];
    admin: boolean;
    busy: boolean;
    onBusy: (value: boolean) => void;
}) {
    const form = useForm({
        status: filters.status || '',
        date_from: filters.date_from || '',
        date_to: filters.date_to || '',
    });
    const index = admin ? adminIndex : residentIndex;
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (busy) {
            return;
        }

        form.get(index.url(), {
            preserveScroll: true,
            onStart: () => onBusy(true),
            onFinish: () => onBusy(false),
        });
    }

    return (
        <form
            onSubmit={submit}
            className="grid gap-4 rounded-xl border p-4"
            aria-label="Filtros de reservas"
        >
            <fieldset
                disabled={busy}
                className="grid min-w-0 gap-4 sm:grid-cols-3"
            >
                <div className="grid gap-2">
                    <Label htmlFor="reservation-status">Status</Label>
                    <Select
                        disabled={busy}
                        value={form.data.status || 'all'}
                        onValueChange={(value) =>
                            form.setData('status', value === 'all' ? '' : value)
                        }
                    >
                        <SelectTrigger
                            id="reservation-status"
                            className="w-full"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos</SelectItem>
                            {statuses.map((status) => (
                                <SelectItem
                                    key={status.value}
                                    value={status.value}
                                >
                                    {status.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.status} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="reservation-from">Data inicial</Label>
                    <Input
                        id="reservation-from"
                        type="date"
                        value={form.data.date_from}
                        onChange={(event) =>
                            form.setData('date_from', event.target.value)
                        }
                    />
                    <InputError message={form.errors.date_from} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="reservation-to">Data final</Label>
                    <Input
                        id="reservation-to"
                        type="date"
                        value={form.data.date_to}
                        onChange={(event) =>
                            form.setData('date_to', event.target.value)
                        }
                    />
                    <InputError message={form.errors.date_to} />
                </div>
            </fieldset>
            <p className="text-sm text-muted-foreground">
                Filtra pela data de início da reserva, incluindo o dia inicial e
                o final.
            </p>
            <div className="flex flex-wrap gap-2">
                <Button type="submit" disabled={busy}>
                    Aplicar filtros
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={busy}
                    onClick={() =>
                        router.get(
                            index.url(),
                            {},
                            {
                                preserveScroll: true,
                                onStart: () => onBusy(true),
                                onFinish: () => onBusy(false),
                            },
                        )
                    }
                >
                    Limpar filtros
                </Button>
            </div>
        </form>
    );
}
