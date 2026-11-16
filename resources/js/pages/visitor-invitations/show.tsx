import { Form, Head } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, QrCode, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import VisitorInvitationShell from '@/components/visitor-invitation-shell';
import { complete } from '@/routes/visitor-invitations';

export default function Invitation({ token }: { token: string }) {
    const [cpf, setCpf] = useState('');
    const [phone, setPhone] = useState('');
    const [vehiclePlate, setVehiclePlate] = useState('');

    return (
        <VisitorInvitationShell>
            <Head title="Cadastro de visitante" />
            <div className="grid w-full gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:items-start lg:gap-10">
                <div className="space-y-5 lg:sticky lg:top-8 lg:py-8">
                    <span className="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary">
                        <ShieldCheck className="size-4" aria-hidden="true" />{' '}
                        Convite para visitante
                    </span>
                    <div className="space-y-3">
                        <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">
                            Sua visita começa aqui
                        </h1>
                        <p className="max-w-md text-sm leading-relaxed text-muted-foreground sm:text-base">
                            Preencha seus dados para gerar o acesso e apresente
                            o QR Code na portaria no dia da visita.
                        </p>
                    </div>
                    <div className="grid gap-3 rounded-2xl border border-border/80 bg-card p-4 text-sm sm:p-5">
                        <p className="flex items-start gap-3">
                            <CheckCircle2
                                className="mt-0.5 size-5 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <span>
                                <strong>1. Informe seus dados</strong>
                                <br />
                                <span className="text-muted-foreground">
                                    Preencha as informações solicitadas.
                                </span>
                            </span>
                        </p>
                        <p className="flex items-start gap-3">
                            <QrCode
                                className="mt-0.5 size-5 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <span>
                                <strong>2. Salve seu QR Code</strong>
                                <br />
                                <span className="text-muted-foreground">
                                    O código será exibido na próxima tela.
                                </span>
                            </span>
                        </p>
                    </div>
                </div>
                <Form
                    {...complete.form(token)}
                    className="grid w-full gap-4 rounded-2xl border border-border/80 bg-card p-5 shadow-sm sm:gap-5 sm:p-7"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="border-b border-border/70 pb-4">
                                <h2 className="text-xl font-semibold">
                                    Complete seu cadastro
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Campos obrigatórios, exceto quando indicado.
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
                            <label className="flex items-start gap-3 rounded-xl border bg-muted/30 p-3 text-sm">
                                <input
                                    name="confirmed"
                                    type="checkbox"
                                    value="1"
                                    required
                                    aria-invalid={Boolean(errors.confirmed)}
                                    aria-describedby={
                                        errors.confirmed
                                            ? 'confirmed-error'
                                            : undefined
                                    }
                                    className="mt-0.5 size-4 shrink-0 accent-primary"
                                />{' '}
                                Confirmo que os dados informados estão corretos.
                            </label>
                            <InputError
                                id="confirmed-error"
                                message={errors.confirmed}
                            />
                            <Button
                                className="h-11 w-full"
                                disabled={processing}
                            >
                                {processing
                                    ? 'Gerando acesso...'
                                    : 'Gerar QR Code'}
                                {!processing && (
                                    <ArrowRight aria-hidden="true" />
                                )}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </VisitorInvitationShell>
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
                aria-describedby={error ? `${name}-error` : undefined}
            />
            <InputError id={`${name}-error`} message={error} />
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
