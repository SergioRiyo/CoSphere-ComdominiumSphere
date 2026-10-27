import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import CoSphereLogo from '@/components/cosphere-logo';
import InputError from '@/components/input-error';
import ThemeToggle from '@/components/theme-toggle';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { complete } from '@/routes/visitor-invitations';

export default function Invitation({ token }: { token: string }) {
    const [cpf, setCpf] = useState('');
    const [phone, setPhone] = useState('');
    const [vehiclePlate, setVehiclePlate] = useState('');

    return (
        <main className="relative grid min-h-svh place-items-center bg-background p-4 sm:p-6">
            <Head title="Cadastro de visitante" />
            <ThemeToggle className="absolute top-4 right-4 sm:top-6 sm:right-6" />
            <div className="w-full max-w-xl">
                <div className="mb-5 flex justify-center">
                    <CoSphereLogo size="md" withTagline />
                </div>
                <Form
                    {...complete.form(token)}
                    className="grid w-full gap-5 rounded-3xl border border-border/80 bg-card/95 p-5 shadow-cosphere-panel backdrop-blur sm:p-7"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="border-b border-border/70 pb-4">
                                <h1 className="text-xl font-semibold">
                                    Complete seu cadastro
                                </h1>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Informe seus dados para receber o QR Code de
                                    acesso.
                                </p>
                            </div>
                            <Field
                                label="Nome completo"
                                name="name"
                                error={errors.name}
                                autoComplete="name"
                                placeholder="Seu nome completo"
                            />
                            <Field
                                label="CPF"
                                name="cpf"
                                error={errors.cpf}
                                value={cpf}
                                onChange={(event) =>
                                    setCpf(formatCpf(event.target.value))
                                }
                                inputMode="numeric"
                                placeholder="000.000.000-00"
                                maxLength={14}
                            />
                            <Field
                                label="Telefone"
                                name="phone"
                                error={errors.phone}
                                value={phone}
                                onChange={(event) =>
                                    setPhone(formatPhone(event.target.value))
                                }
                                inputMode="tel"
                                autoComplete="tel"
                                placeholder="(65) 99999-9999"
                                maxLength={15}
                            />
                            <Field
                                label="Placa do veículo (opcional)"
                                name="vehicle_plate"
                                error={errors.vehicle_plate}
                                value={vehiclePlate}
                                onChange={(event) =>
                                    setVehiclePlate(
                                        formatPlate(event.target.value),
                                    )
                                }
                                placeholder="ABC-1D23"
                                maxLength={8}
                            />
                            <label className="flex items-start gap-2 text-sm">
                                <input
                                    name="confirmed"
                                    type="checkbox"
                                    value="1"
                                    required
                                    aria-invalid={Boolean(errors.confirmed)}
                                    className="mt-0.5 size-4 shrink-0 accent-primary"
                                />{' '}
                                Confirmo que os dados estão corretos.
                            </label>
                            <InputError message={errors.confirmed} />
                            <Button className="w-full" disabled={processing}>
                                {processing ? 'Enviando...' : 'Gerar acesso'}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </main>
    );
}
function Field({
    label,
    name,
    error,
    ...props
}: {
    label: string;
    name: string;
    error?: string;
} & React.ComponentProps<typeof Input>) {
    return (
        <div className="grid gap-1">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                {...props}
                required={name !== 'vehicle_plate'}
                aria-invalid={Boolean(error)}
            />
            <InputError message={error} />
        </div>
    );
}

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
