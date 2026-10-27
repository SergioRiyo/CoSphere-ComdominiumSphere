import { Head, Link, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    archive,
    index,
    store,
    update,
} from '@/routes/admin/service-providers';
import type { Provider } from '@/types/maintenance';
export default function ProviderForm({
    provider,
}: {
    provider: Provider | null;
}) {
    const lock = useRef(false);
    const [confirm, setConfirm] = useState(false);
    const form = useForm({
        name: provider?.name || '',
        cpf_cnpj: provider?.cpf_cnpj || '',
        phone: provider?.phone || '',
        email: provider?.email || '',
        specialty: provider?.specialty || '',
    });
    const archival = useForm({});
    const archived = Boolean(provider?.deleted_at);
    const busy = form.processing || archival.processing;
    const fields = [
        { key: 'name', label: 'Nome', required: true },
        { key: 'phone', label: 'Telefone' },
        { key: 'email', label: 'E-mail' },
        { key: 'specialty', label: 'Especialidade' },
        { key: 'cpf_cnpj', label: 'CPF/CNPJ (opcional)' },
    ] as const;

    return (
        <>
            <Head title={provider ? 'Prestador' : 'Novo prestador'} />
            <div className="grid gap-6 p-4 sm:p-6">
                <Button asChild variant="outline" className="w-fit">
                    <Link href={index()}>Voltar aos prestadores</Link>
                </Button>
                <h1 className="text-2xl font-semibold">
                    {provider?.name || 'Novo prestador'}
                </h1>
                {archived && (
                    <p className="rounded-lg border p-4">
                        Prestador arquivado. Os vínculos históricos foram
                        preservados.
                    </p>
                )}
                <form
                    className="grid max-w-3xl gap-5 rounded-xl border p-5"
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (lock.current || archived) {
                            return;
                        }

                        lock.current = true;
                        const options = {
                            onSuccess: () => toast.success('Prestador salvo.'),
                            onError: () => toast.error('Confira os campos.'),
                            onFinish: () => {
                                lock.current = false;
                            },
                        };

                        if (provider) {
                            form.patch(update.url(provider.id), options);
                        } else {
                            form.post(store.url(), options);
                        }
                    }}
                >
                    <fieldset
                        disabled={busy || archived}
                        className="grid gap-4 sm:grid-cols-2"
                    >
                        {fields.map((field) => (
                            <div key={field.key} className="grid gap-2">
                                <Label htmlFor={field.key}>{field.label}</Label>
                                <Input
                                    id={field.key}
                                    value={form.data[field.key]}
                                    required={field.key === 'name'}
                                    type={
                                        field.key === 'email' ? 'email' : 'text'
                                    }
                                    maxLength={255}
                                    onChange={(event) =>
                                        form.setData(
                                            field.key,
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors[field.key]} />
                            </div>
                        ))}
                    </fieldset>
                    {!archived && (
                        <div className="flex flex-wrap gap-3">
                            <Button disabled={busy}>
                                {form.processing
                                    ? 'Salvando...'
                                    : 'Salvar prestador'}
                            </Button>
                            {provider && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={busy}
                                    onClick={() => setConfirm(true)}
                                >
                                    Arquivar prestador
                                </Button>
                            )}
                        </div>
                    )}
                </form>
                <Dialog open={confirm} onOpenChange={setConfirm}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Arquivar prestador?</DialogTitle>
                            <DialogDescription>
                                Ele deixará de aceitar novas atribuições. As
                                manutenções e seus históricos continuarão
                                disponíveis.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                disabled={busy}
                                onClick={() => setConfirm(false)}
                            >
                                Voltar
                            </Button>
                            <Button
                                disabled={busy}
                                onClick={() => {
                                    if (!provider || lock.current) {
                                        return;
                                    }

                                    lock.current = true;
                                    archival.patch(archive.url(provider.id), {
                                        onSuccess: () =>
                                            toast.success(
                                                'Prestador arquivado.',
                                            ),
                                        onError: () =>
                                            toast.error(
                                                'Não foi possível arquivar.',
                                            ),
                                        onFinish: () => {
                                            lock.current = false;
                                            setConfirm(false);
                                        },
                                    });
                                }}
                            >
                                Confirmar arquivamento
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
ProviderForm.layout = {
    breadcrumbs: [{ title: 'Prestadores', href: index() }],
};
