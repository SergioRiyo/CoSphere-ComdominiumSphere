import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { complete } from '@/routes/visitor-invitations';

export default function Invitation({ token }: { token: string }) {
    return (
        <main className="mx-auto grid min-h-svh max-w-xl place-items-center p-6">
            <Head title="Cadastro de visitante" />
            <Form
                {...complete.form(token)}
                className="grid w-full gap-4 rounded-2xl border bg-card p-6 shadow-sm"
            >
                {({ errors, processing }) => (
                    <>
                        <div>
                            <h1 className="text-xl font-semibold">
                                Complete seu cadastro
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Informe seus dados para receber o QR Code de
                                acesso.
                            </p>
                        </div>
                        <Field
                            label="Nome completo"
                            name="name"
                            error={errors.name}
                        />
                        <Field
                            label="CPF"
                            name="cpf"
                            error={errors.cpf}
                            inputMode="numeric"
                        />
                        <Field
                            label="Telefone"
                            name="phone"
                            error={errors.phone}
                            inputMode="tel"
                        />
                        <Field
                            label="Placa do veículo (opcional)"
                            name="vehicle_plate"
                            error={errors.vehicle_plate}
                        />
                        <label className="flex gap-2 text-sm">
                            <input
                                name="confirmed"
                                type="checkbox"
                                value="1"
                                required
                            />{' '}
                            Confirmo que os dados estão corretos.
                        </label>
                        <InputError message={errors.confirmed} />
                        <Button disabled={processing}>
                            {processing ? 'Enviando...' : 'Gerar acesso'}
                        </Button>
                    </>
                )}
            </Form>
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
            />
            <InputError message={error} />
        </div>
    );
}
