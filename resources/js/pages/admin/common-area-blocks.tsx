import { Head, Link, router, useHttp } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes/admin';
import { index, store, destroy } from '@/routes/admin/common-area-blocks';
import { index as areasIndex } from '@/routes/admin/common-areas';

type Block = {
    id: number;
    area: string;
    date: string;
    start: string;
    end: string;
    reason: string;
    admin: string;
};
type Props = {
    areas: { id: number; name: string }[];
    today: string;
    blocks: {
        data: Block[];
        current_page: number;
        last_page: number;
        total: number;
    };
};

export default function CommonAreaBlocks({ areas, today, blocks }: Props) {
    const [date, setDate] = useState(today);
    const [selection, setSelection] = useState<Block | null>(null);
    const [busy, setBusy] = useState(false);
    const submitting = useRef(false);
    const form = useHttp<
        {
            common_area_id: string;
            starts_at: string;
            ends_at: string;
            reason: string;
        },
        { message: string }
    >({ common_area_id: '', starts_at: '', ends_at: '', reason: '' });
    const removal = useHttp<Record<string, never>, { message: string }>({});

    async function submit(event: FormEvent<HTMLFormElement>, removing = false) {
        event.preventDefault();

        if (submitting.current || (removing && !selection)) {
            return;
        }

        submitting.current = true;
        setBusy(true);
        let validationFailed = false;
        const options = {
            onError: (errors: Record<string, string>) => {
                validationFailed = true;
                toast.error(
                    Object.values(errors)[0] ||
                        'Não foi possível salvar a alteração.',
                );
            },
        };

        try {
            form.transform((data) => ({
                ...data,
                starts_at: `${date} ${data.starts_at}`,
                ends_at: `${date} ${data.ends_at}`,
            }));
            const response =
                removing && selection
                    ? await removal.delete(destroy.url(selection.id), options)
                    : await form.post(store.url(), options);
            toast.success(response.message);

            if (removing) {
                setSelection(null);
            } else {
                form.reset('starts_at', 'ends_at', 'reason');
            }
        } catch {
            if (!validationFailed) {
                toast.error(
                    'Não foi possível salvar a alteração. Atualize a lista e tente novamente.',
                );
            }
        } finally {
            router.reload({
                only: ['blocks', 'areas'],
                onFinish: () => {
                    setBusy(false);
                    submitting.current = false;
                },
            });
        }
    }

    return (
        <>
            <Head title="Bloqueios de áreas" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="grid gap-2">
                        <h1 className="text-2xl font-semibold">
                            Bloqueios de áreas
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Indisponibilidade por período. Resolva reservas
                            pendentes ou aprovadas antes de bloquear.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={areasIndex()}>Gerenciar áreas</Link>
                    </Button>
                </header>
                <Card>
                    <CardHeader>
                        <CardTitle>Novo bloqueio</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {areas.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Não há áreas ativas para bloquear.
                            </p>
                        ) : (
                            <form onSubmit={submit} className="grid gap-4">
                                <fieldset
                                    disabled={busy}
                                    className="grid min-w-0 gap-4 sm:grid-cols-2"
                                >
                                    <div className="grid gap-2">
                                        <Label htmlFor="block-area">Área</Label>
                                        <Select
                                            disabled={busy}
                                            value={form.data.common_area_id}
                                            onValueChange={(value) =>
                                                form.setData(
                                                    'common_area_id',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                id="block-area"
                                                className="w-full"
                                            >
                                                <SelectValue placeholder="Selecione uma área" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {areas.map((area) => (
                                                    <SelectItem
                                                        key={area.id}
                                                        value={String(area.id)}
                                                    >
                                                        {area.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={form.errors.common_area_id}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="block-date">Data</Label>
                                        <Input
                                            id="block-date"
                                            type="date"
                                            required
                                            value={date}
                                            onChange={(event) =>
                                                setDate(event.target.value)
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="block-start">
                                            Início
                                        </Label>
                                        <Input
                                            id="block-start"
                                            type="time"
                                            step="1"
                                            required
                                            value={form.data.starts_at}
                                            onChange={(event) =>
                                                form.setData(
                                                    'starts_at',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={form.errors.starts_at}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="block-end">
                                            Fim (mesmo dia)
                                        </Label>
                                        <Input
                                            id="block-end"
                                            type="time"
                                            step="1"
                                            required
                                            value={form.data.ends_at}
                                            onChange={(event) =>
                                                form.setData(
                                                    'ends_at',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={form.errors.ends_at}
                                        />
                                    </div>
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="block-reason">
                                            Motivo interno
                                        </Label>
                                        <Input
                                            id="block-reason"
                                            required
                                            maxLength={255}
                                            value={form.data.reason}
                                            onChange={(event) =>
                                                form.setData(
                                                    'reason',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={form.errors.reason}
                                        />
                                    </div>
                                </fieldset>
                                <Button
                                    type="submit"
                                    className="justify-self-start"
                                    disabled={busy || !form.data.common_area_id}
                                >
                                    Criar bloqueio
                                </Button>
                            </form>
                        )}
                    </CardContent>
                </Card>
                <section
                    aria-label="Bloqueios cadastrados"
                    aria-busy={busy}
                    className="grid gap-4"
                >
                    <div aria-live="polite">
                        {busy && (
                            <p
                                role="status"
                                className="text-sm text-muted-foreground"
                            >
                                Atualizando bloqueios…
                            </p>
                        )}
                    </div>
                    {blocks.data.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Nenhum bloqueio cadastrado.
                        </p>
                    )}
                    {blocks.data.map((block) => (
                        <Card key={block.id}>
                            <CardHeader>
                                <CardTitle className="break-words">
                                    {block.area}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div className="grid min-w-0 gap-2 text-sm">
                                    <p>
                                        {block.date
                                            .split('-')
                                            .reverse()
                                            .join('/')}{' '}
                                        · {block.start} → {block.end}
                                    </p>
                                    <p className="break-words">
                                        Motivo: {block.reason}
                                    </p>
                                    <p className="break-words text-muted-foreground">
                                        Responsável: {block.admin}
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    disabled={busy}
                                    onClick={() => setSelection(block)}
                                >
                                    Remover
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </section>
                {blocks.last_page > 1 && (
                    <nav
                        aria-label="Paginação dos bloqueios"
                        className="flex flex-wrap items-center gap-3"
                    >
                        <Button
                            asChild
                            variant="outline"
                            disabled={busy || blocks.current_page === 1}
                        >
                            <Link
                                href={index({
                                    query: {
                                        page: Math.max(
                                            1,
                                            blocks.current_page - 1,
                                        ),
                                    },
                                })}
                                onBefore={() =>
                                    !busy && blocks.current_page > 1
                                }
                            >
                                Anterior
                            </Link>
                        </Button>
                        <span className="text-sm">
                            Página {blocks.current_page} de {blocks.last_page}
                        </span>
                        <Button
                            asChild
                            variant="outline"
                            disabled={
                                busy || blocks.current_page === blocks.last_page
                            }
                        >
                            <Link
                                href={index({
                                    query: {
                                        page: Math.min(
                                            blocks.last_page,
                                            blocks.current_page + 1,
                                        ),
                                    },
                                })}
                                onBefore={() =>
                                    !busy &&
                                    blocks.current_page < blocks.last_page
                                }
                            >
                                Próxima
                            </Link>
                        </Button>
                    </nav>
                )}
            </div>
            <Dialog
                open={selection !== null}
                onOpenChange={(open) => {
                    if (!open && !busy) {
                        setSelection(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Remover bloqueio?</DialogTitle>
                        <DialogDescription>
                            Remover o bloqueio de {selection?.area} em{' '}
                            {selection?.date.split('-').reverse().join('/')} (
                            {selection?.start} → {selection?.end})? A
                            disponibilidade será recalculada considerando
                            reservas, outros bloqueios e a situação da área.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={(event) => submit(event, true)}>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={busy}
                                onClick={() => setSelection(null)}
                            >
                                Voltar
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={busy}
                            >
                                Remover bloqueio
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

CommonAreaBlocks.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Bloqueios de áreas', href: index() },
    ],
};
