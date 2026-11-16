import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import LostFoundForm from '@/components/lost-found-form';
import { Button } from '@/components/ui/button';
import { dateText, statusLabels } from '@/types/lost-found';
import type { LostFoundItem } from '@/types/lost-found';
import {
    index as adminIndex,
    show as adminShow,
    photo as adminPhoto,
} from '@/routes/admin/lost-found';
import {
    index as residentIndex,
    show as residentShow,
    photo as residentPhoto,
} from '@/routes/morador/lost-found';

type Props = {
    items: { data: LostFoundItem[]; current_page: number; last_page: number };
    filters: { status?: string; type?: string };
};
export default function LostFoundIndex({ items, filters }: Props) {
    const admin = usePage().props.auth.user.role === 'admin';
    const index = admin ? adminIndex : residentIndex;
    const [open, setOpen] = useState(false);
    const [status, setStatus] = useState(filters.status ?? 'active');
    const [type, setType] = useState(filters.type ?? '');

    return (
        <>
            <Head title="Achados e perdidos" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Achados e perdidos
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Consulte os itens e procure a administração para
                            intermediar a devolução.
                        </p>
                    </div>
                    <Button onClick={() => setOpen(true)}>
                        Registrar item
                    </Button>
                </header>
                <form
                    className="flex flex-wrap items-end gap-3 rounded-xl border p-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get(
                            index.url(),
                            { status, type },
                            { preserveState: true },
                        );
                    }}
                >
                    <label className="grid gap-2 text-sm">
                        Situação
                        <select
                            className="h-10 min-w-48 rounded-md border bg-background px-3"
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                        >
                            <option value="active">
                                Abertos e em atendimento
                            </option>
                            <option value="resolved">Finalizados</option>
                        </select>
                    </label>
                    <label className="grid gap-2 text-sm">
                        Tipo
                        <select
                            className="h-10 min-w-36 rounded-md border bg-background px-3"
                            value={type}
                            onChange={(event) => setType(event.target.value)}
                        >
                            <option value="">Todos</option>
                            <option value="lost">Perdidos</option>
                            <option value="found">Encontrados</option>
                        </select>
                    </label>
                    <Button>Aplicar filtros</Button>
                </form>
                <section
                    className="grid gap-4 lg:grid-cols-2"
                    aria-label="Itens"
                >
                    {items.data.length === 0 && (
                        <p className="rounded-xl border p-6 text-center text-muted-foreground lg:col-span-2">
                            Nenhum item encontrado.
                        </p>
                    )}
                    {items.data.map((item) => (
                        <article
                            key={item.id}
                            className="flex flex-col gap-3 rounded-xl border p-4 sm:flex-row"
                        >
                            {item.has_photo && (
                                <img
                                    className="h-32 w-full rounded-md object-cover sm:w-32"
                                    src={
                                        admin
                                            ? adminPhoto.url(item.id)
                                            : residentPhoto.url(item.id)
                                    }
                                    alt="Foto do item"
                                />
                            )}
                            <div className="grid flex-1 gap-2">
                                <div className="flex flex-wrap gap-2 text-xs font-medium">
                                    <span className="rounded bg-primary/10 px-2 py-1 text-primary">
                                        {item.type === 'lost'
                                            ? 'Perdido'
                                            : 'Encontrado'}
                                    </span>
                                    <span className="rounded bg-muted px-2 py-1">
                                        {statusLabels[item.status]}
                                    </span>
                                    {item.is_author && (
                                        <span className="rounded bg-muted px-2 py-1">
                                            Seu registro
                                        </span>
                                    )}
                                </div>
                                <p className="break-words whitespace-pre-wrap">
                                    {item.description}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {item.location} ·{' '}
                                    {dateText(item.event_date)}
                                </p>
                                {admin && Boolean(item.conversations_count) && (
                                    <p className="text-sm">
                                        {item.conversations_count}{' '}
                                        manifestação(ões)
                                    </p>
                                )}
                                <Button
                                    variant="outline"
                                    asChild
                                    className="justify-self-start"
                                >
                                    <Link
                                        href={
                                            admin
                                                ? adminShow(item.id)
                                                : residentShow(item.id)
                                        }
                                    >
                                        Ver detalhes
                                    </Link>
                                </Button>
                            </div>
                        </article>
                    ))}
                </section>
                <nav className="flex items-center gap-3" aria-label="Paginação">
                    {items.current_page > 1 && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.get(index.url(), {
                                    ...filters,
                                    page: items.current_page - 1,
                                })
                            }
                        >
                            Anterior
                        </Button>
                    )}
                    <span className="text-sm">
                        Página {items.current_page} de {items.last_page}
                    </span>
                    {items.current_page < items.last_page && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.get(index.url(), {
                                    ...filters,
                                    page: items.current_page + 1,
                                })
                            }
                        >
                            Próxima
                        </Button>
                    )}
                </nav>
            </div>
            <LostFoundForm
                key={String(open)}
                admin={admin}
                open={open}
                onOpenChange={setOpen}
            />
        </>
    );
}
