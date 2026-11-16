import { useForm } from '@inertiajs/react';
import { ImagePlus, X } from 'lucide-react';
import { useRef } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    store as adminStore,
    update as adminUpdate,
} from '@/routes/admin/lost-found';
import {
    store as residentStore,
    update as residentUpdate,
} from '@/routes/morador/lost-found';
import type { LostFoundItem } from '@/types/lost-found';

export default function LostFoundForm({
    item,
    admin,
    open,
    onOpenChange,
}: {
    item?: LostFoundItem;
    admin: boolean;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const photoInputRef = useRef<HTMLInputElement>(null);
    const form = useForm({
        type: item?.type ?? 'lost',
        description: item?.description ?? '',
        condition: item?.condition ?? '',
        location: item?.location ?? '',
        event_date: item?.event_date ?? '',
        photo: null as File | null,
        remove_photo: false,
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => (item ? { ...data, _method: 'PUT' } : data));
        const url = item
            ? admin
                ? adminUpdate.url(item.id)
                : residentUpdate.url(item.id)
            : admin
              ? adminStore.url()
              : residentStore.url();
        form.post(url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();

                if (photoInputRef.current) {
                    photoInputRef.current.value = '';
                }

                onOpenChange(false);
            },
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                if (!form.processing) {
                    onOpenChange(value);
                }
            }}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {item ? 'Editar item' : 'Registrar item'}
                    </DialogTitle>
                    <DialogDescription>
                        Informe os dados para ajudar a identificar o item.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <label className="grid gap-1 text-sm">
                        Tipo
                        <select
                            required
                            className="h-10 rounded-md border bg-background px-3"
                            value={form.data.type}
                            onChange={(event) =>
                                form.setData(
                                    'type',
                                    event.target.value as 'lost' | 'found',
                                )
                            }
                        >
                            <option value="lost">Perdi</option>
                            <option value="found">Encontrei</option>
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">
                        Descrição
                        <textarea
                            required
                            maxLength={1000}
                            className="min-h-24 rounded-md border bg-background p-3"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                    </label>
                    <label className="grid gap-1 text-sm">
                        Condição
                        <input
                            required
                            maxLength={255}
                            className="h-10 rounded-md border bg-background px-3"
                            value={form.data.condition}
                            onChange={(event) =>
                                form.setData('condition', event.target.value)
                            }
                        />
                    </label>
                    <label className="grid gap-1 text-sm">
                        Local
                        <input
                            required
                            maxLength={255}
                            className="h-10 rounded-md border bg-background px-3"
                            value={form.data.location}
                            onChange={(event) =>
                                form.setData('location', event.target.value)
                            }
                        />
                    </label>
                    <label className="grid gap-1 text-sm">
                        Data da perda ou localização
                        <input
                            required
                            type="date"
                            className="h-10 rounded-md border bg-background px-3"
                            value={form.data.event_date}
                            onChange={(event) =>
                                form.setData('event_date', event.target.value)
                            }
                        />
                    </label>
                    <div className="grid gap-2 text-sm">
                        <span>Foto opcional</span>
                        <input
                            ref={photoInputRef}
                            type="file"
                            className="hidden"
                            accept="image/jpeg,image/png,image/webp"
                            onChange={(event) =>
                                form.setData(
                                    'photo',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <div className="flex min-w-0 flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => photoInputRef.current?.click()}
                            >
                                <ImagePlus aria-hidden="true" />
                                {form.data.photo
                                    ? 'Trocar foto'
                                    : 'Adicionar foto'}
                            </Button>
                            {form.data.photo && (
                                <>
                                    <span
                                        className="min-w-0 flex-1 truncate text-muted-foreground"
                                        title={form.data.photo.name}
                                    >
                                        {form.data.photo.name}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Remover foto selecionada"
                                        onClick={() => {
                                            form.setData('photo', null);

                                            if (photoInputRef.current) {
                                                photoInputRef.current.value =
                                                    '';
                                            }
                                        }}
                                    >
                                        <X aria-hidden="true" />
                                    </Button>
                                </>
                            )}
                        </div>
                        <p className="text-xs text-muted-foreground">
                            JPEG, PNG ou WebP, até 5 MB.
                        </p>
                    </div>
                    {item?.has_photo && (
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.remove_photo}
                                onChange={(event) =>
                                    form.setData(
                                        'remove_photo',
                                        event.target.checked,
                                    )
                                }
                            />
                            Remover foto atual
                        </label>
                    )}
                    {form.hasErrors && (
                        <div role="alert" className="text-sm text-destructive">
                            {Object.values(form.errors).map((error, index) => (
                                <p key={index}>{error}</p>
                            ))}
                        </div>
                    )}
                    <Button disabled={form.processing}>
                        {form.processing ? 'Salvando…' : 'Salvar item'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
