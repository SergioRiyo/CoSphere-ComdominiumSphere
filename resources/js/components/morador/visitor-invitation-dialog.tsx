import { Form } from '@inertiajs/react';
import { Share2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/morador/visitor-invitations';

export default function VisitorInvitationDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [start, setStart] = useState('');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Novo convite</DialogTitle>
                    <DialogDescription>
                        Defina o período. O visitante preencherá os próprios
                        dados.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...store.form()}
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="invitation-start">Início</Label>
                                <Input
                                    id="invitation-start"
                                    name="start_date"
                                    type="datetime-local"
                                    value={start}
                                    onChange={(event) =>
                                        setStart(event.target.value)
                                    }
                                    required
                                />
                                <p className="text-sm text-destructive">
                                    {errors.start_date}
                                </p>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="invitation-end">Término</Label>
                                <Input
                                    id="invitation-end"
                                    name="end_date"
                                    type="datetime-local"
                                    min={start}
                                    required
                                />
                                <p className="text-sm text-destructive">
                                    {errors.end_date}
                                </p>
                            </div>
                            <Button disabled={processing}>
                                {processing ? 'Gerando...' : 'Gerar link'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export function InvitationLinkDialog({
    url,
    onOpenChange,
}: {
    url: string | null;
    onOpenChange: (open: boolean) => void;
}) {
    const copy = async () => {
        if (url) {
            await navigator.clipboard.writeText(url);
        }
    };
    const share = async () => {
        if (url && navigator.share) {
            await navigator.share({ url });
        } else {
            await copy();
        }
    };

    return (
        <Dialog open={Boolean(url)} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Link do convite</DialogTitle>
                    <DialogDescription>
                        Compartilhe agora. Este link não fica disponível
                        novamente.
                    </DialogDescription>
                </DialogHeader>
                <Input value={url ?? ''} readOnly />
                <div className="flex gap-2">
                    <Button type="button" onClick={copy}>
                        Copiar link
                    </Button>
                    <Button type="button" variant="outline" onClick={share}>
                        <Share2 />
                        Compartilhar
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
