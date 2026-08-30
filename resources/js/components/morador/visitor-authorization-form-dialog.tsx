import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/VisitorAuthorizationController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type VisitorAuthorizationFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    timezone: string;
};

function formatCpf(value: string): string {
    return value
        .replace(/\D/g, '')
        .slice(0, 11)
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
}

function formatPhone(value: string): string {
    const digits = value.replace(/\D/g, '').slice(0, 11);

    if (digits.length < 3) {
        return digits;
    }

    if (digits.length < 7) {
        return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    }

    return digits.length === 11
        ? `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`
        : `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
}

function formatPlate(value: string): string {
    const plate = value
        .replace(/[^a-zA-Z0-9]/g, '')
        .slice(0, 7)
        .toUpperCase();

    return plate.length > 3 ? `${plate.slice(0, 3)}-${plate.slice(3)}` : plate;
}

function dateTimeValue(hoursFromNow: number): string {
    const date = new Date(Date.now() + hoursFromNow * 60 * 60 * 1000);

    date.setSeconds(0, 0);

    return date.toISOString().slice(0, 16);
}

export default function VisitorAuthorizationFormDialog({
    open,
    onOpenChange,
    timezone,
}: VisitorAuthorizationFormDialogProps) {
    const [cpf, setCpf] = useState('');
    const [phone, setPhone] = useState('');
    const [plate, setPlate] = useState('');
    const initialStartDate = dateTimeValue(1);
    const initialEndDate = dateTimeValue(2);

    const handleOpenChange = (nextOpen: boolean) => {
        if (!nextOpen) {
            setCpf('');
            setPhone('');
            setPlate('');
        }

        onOpenChange(nextOpen);
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Nova autorização</DialogTitle>
                    <DialogDescription>
                        Informe os dados do visitante e o período em que o
                        acesso será permitido.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    key={open ? 'open' : 'closed'}
                    {...store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    onSuccess={() => handleOpenChange(false)}
                    className="grid gap-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="visitor-name">
                                        Nome completo
                                    </Label>
                                    <Input
                                        id="visitor-name"
                                        name="name"
                                        required
                                        autoComplete="name"
                                        placeholder="Nome do visitante"
                                        aria-invalid={Boolean(errors.name)}
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="visitor-cpf">CPF</Label>
                                    <Input
                                        id="visitor-cpf"
                                        name="cpf"
                                        value={cpf}
                                        onChange={(event) =>
                                            setCpf(
                                                formatCpf(event.target.value),
                                            )
                                        }
                                        required
                                        inputMode="numeric"
                                        placeholder="000.000.000-00"
                                        maxLength={14}
                                        aria-invalid={Boolean(errors.cpf)}
                                    />
                                    <InputError message={errors.cpf} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="visitor-phone">
                                        Telefone
                                    </Label>
                                    <Input
                                        id="visitor-phone"
                                        name="phone"
                                        value={phone}
                                        onChange={(event) =>
                                            setPhone(
                                                formatPhone(event.target.value),
                                            )
                                        }
                                        required
                                        inputMode="tel"
                                        autoComplete="tel"
                                        placeholder="(65) 99999-9999"
                                        maxLength={15}
                                        aria-invalid={Boolean(errors.phone)}
                                    />
                                    <InputError message={errors.phone} />
                                </div>

                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="visitor-plate">
                                        Placa do veículo
                                        <span className="text-muted-foreground">
                                            {' '}
                                            (opcional)
                                        </span>
                                    </Label>
                                    <Input
                                        id="visitor-plate"
                                        name="vehicle_plate"
                                        value={plate}
                                        onChange={(event) =>
                                            setPlate(
                                                formatPlate(event.target.value),
                                            )
                                        }
                                        placeholder="ABC-1D23"
                                        maxLength={8}
                                        aria-invalid={Boolean(
                                            errors.vehicle_plate,
                                        )}
                                    />
                                    <InputError
                                        message={errors.vehicle_plate}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="visitor-start-date">
                                        Início
                                    </Label>
                                    <Input
                                        id="visitor-start-date"
                                        name="start_date"
                                        type="datetime-local"
                                        defaultValue={initialStartDate}
                                        min={dateTimeValue(0)}
                                        required
                                        aria-invalid={Boolean(
                                            errors.start_date,
                                        )}
                                    />
                                    <InputError message={errors.start_date} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="visitor-end-date">
                                        Término
                                    </Label>
                                    <Input
                                        id="visitor-end-date"
                                        name="end_date"
                                        type="datetime-local"
                                        defaultValue={initialEndDate}
                                        min={initialStartDate}
                                        required
                                        aria-invalid={Boolean(errors.end_date)}
                                    />
                                    <InputError message={errors.end_date} />
                                </div>
                            </div>

                            <p className="text-xs text-muted-foreground">
                                Os horários são registrados no timezone da
                                aplicação: {timezone}.
                            </p>

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Autorizando...'
                                        : 'Autorizar visitante'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
