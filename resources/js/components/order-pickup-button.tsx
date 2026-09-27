import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export function OrderPickupButton({
    action,
    orderId,
    label,
}: {
    action: string;
    orderId: number;
    label: string;
}) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const fail = () => {
        setError(
            'Não foi possível confirmar a retirada. Verifique sua conexão e tente novamente.',
        );

        return false;
    };

    return (
        <>
            <Button
                className="self-start"
                onClick={() => {
                    setError(null);
                    setOpen(true);
                }}
            >
                {label}
            </Button>
            <Dialog
                open={open}
                onOpenChange={(value) => {
                    if (!busy) {
                        setOpen(value);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Confirmar retirada da encomenda #{orderId}
                        </DialogTitle>
                        <DialogDescription>
                            Confirme somente após a entrega física do pacote.
                            Seu usuário será registrado como responsável pela
                            confirmação, sem identificar separadamente quem
                            levou o pacote.
                        </DialogDescription>
                    </DialogHeader>
                    {error && (
                        <p role="alert" className="text-sm text-destructive">
                            {error}
                        </p>
                    )}
                    <Form
                        action={action}
                        method="patch"
                        options={{ preserveScroll: true }}
                        onStart={() => setBusy(true)}
                        onFinish={() => setBusy(false)}
                        onSuccess={() => setOpen(false)}
                        onHttpException={fail}
                        onNetworkError={fail}
                    >
                        {({ errors, processing }) => (
                            <div className="flex flex-col gap-4">
                                {Object.entries(errors).map(
                                    ([field, message]) => (
                                        <InputError
                                            key={field}
                                            message={message}
                                            role="alert"
                                        />
                                    ),
                                )}
                                <div className="flex gap-2">
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Confirmando...'
                                            : 'Confirmar retirada'}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                        onClick={() => setOpen(false)}
                                    >
                                        Cancelar
                                    </Button>
                                </div>
                            </div>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}
