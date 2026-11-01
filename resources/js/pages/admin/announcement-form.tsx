import { Head, Link, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
import { index, publish, store, update } from '@/routes/admin/announcements';
import { formatAnnouncementDate } from '@/types/announcement';
import type { Announcement } from '@/types/announcement';

export default function AnnouncementForm({
    announcement,
    timezone,
}: {
    announcement: Announcement | null;
    timezone: string;
}) {
    const form = useForm({
        title: announcement?.title ?? '',
        content: announcement?.content ?? '',
    });
    const publication = useForm({});
    const [confirm, setConfirm] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const lock = useRef(false);
    const published = Boolean(announcement?.published_at);
    const busy = form.processing || publication.processing;

    return (
        <>
            <Head title={announcement ? 'Comunicado' : 'Novo comunicado'} />
            <div className="grid gap-6 p-4 sm:p-6">
                <Button asChild variant="outline" className="w-fit">
                    <Link href={index()}>Voltar aos comunicados</Link>
                </Button>
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="text-2xl font-semibold">
                        {announcement ? 'Comunicado' : 'Novo comunicado'}
                    </h1>
                    <Badge variant={published ? 'default' : 'outline'}>
                        {published ? 'Publicado' : 'Rascunho'}
                    </Badge>
                </div>
                {announcement && (
                    <p className="text-sm text-muted-foreground">
                        Por {announcement.author}
                        {announcement.published_at && (
                            <>
                                {' '}
                                · Publicado em{' '}
                                {formatAnnouncementDate(
                                    announcement.published_at,
                                    timezone,
                                )}
                            </>
                        )}
                    </p>
                )}
                {error && (
                    <p role="alert" className="text-sm text-destructive">
                        {error}
                    </p>
                )}
                {published && announcement ? (
                    <section className="grid max-w-3xl min-w-0 gap-4 rounded-xl border p-5 break-words">
                        <p className="text-sm text-muted-foreground">
                            Este comunicado está publicado e disponível apenas
                            para consulta.
                        </p>
                        <h2 className="text-xl font-semibold">
                            {announcement.title}
                        </h2>
                        <p className="whitespace-pre-wrap">
                            {announcement.content}
                        </p>
                    </section>
                ) : (
                    <form
                        className="grid max-w-3xl gap-5 rounded-xl border p-5"
                        onSubmit={(event) => {
                            event.preventDefault();

                            if (lock.current) {
                                return;
                            }

                            lock.current = true;
                            setError(null);
                            const options = {
                                preserveScroll: true,
                                onError: () =>
                                    setError(
                                        'Confira os campos e tente novamente.',
                                    ),
                                onHttpException: () => {
                                    setError(
                                        'Não foi possível salvar o rascunho. Seus dados foram preservados.',
                                    );

                                    return false as const;
                                },
                                onNetworkError: () => {
                                    setError(
                                        'Não foi possível conectar ao servidor. Seus dados foram preservados.',
                                    );

                                    return false as const;
                                },
                                onFinish: () => {
                                    lock.current = false;
                                },
                            };

                            if (announcement) {
                                form.patch(
                                    update.url(announcement.id),
                                    options,
                                );
                            } else {
                                form.post(store.url(), options);
                            }
                        }}
                    >
                        <fieldset
                            disabled={busy}
                            className="grid min-w-0 gap-4"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="title">Título</Label>
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    maxLength={255}
                                    required
                                    aria-invalid={Boolean(form.errors.title)}
                                    onChange={(event) =>
                                        form.setData(
                                            'title',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.title} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="content">Conteúdo</Label>
                                <textarea
                                    id="content"
                                    value={form.data.content}
                                    required
                                    rows={10}
                                    aria-invalid={Boolean(form.errors.content)}
                                    onChange={(event) =>
                                        form.setData(
                                            'content',
                                            event.target.value,
                                        )
                                    }
                                    className="min-h-40 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm"
                                />
                                <InputError message={form.errors.content} />
                            </div>
                        </fieldset>
                        <div className="flex flex-wrap gap-3">
                            <Button disabled={busy}>
                                {form.processing
                                    ? 'Salvando...'
                                    : 'Salvar rascunho'}
                            </Button>
                            {announcement && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={busy || form.isDirty}
                                    onClick={() => {
                                        setError(null);
                                        setConfirm(true);
                                    }}
                                >
                                    Publicar comunicado
                                </Button>
                            )}
                        </div>
                        {announcement && form.isDirty && (
                            <p className="text-sm text-muted-foreground">
                                Salve as alterações antes de publicar.
                            </p>
                        )}
                    </form>
                )}
                <Dialog
                    open={confirm}
                    onOpenChange={(open) => {
                        if (!busy) {
                            setConfirm(open);
                        }
                    }}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Publicar comunicado?</DialogTitle>
                            <DialogDescription>
                                Após a publicação, o comunicado não poderá ser
                                alterado. Moradores ativos com e-mail verificado
                                receberão uma notificação.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={busy}
                                onClick={() => setConfirm(false)}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="button"
                                disabled={busy}
                                onClick={() => {
                                    if (
                                        !announcement ||
                                        form.isDirty ||
                                        lock.current
                                    ) {
                                        return;
                                    }

                                    lock.current = true;
                                    setError(null);
                                    publication.patch(
                                        publish.url(announcement.id),
                                        {
                                            preserveScroll: true,
                                            onSuccess: () => setConfirm(false),
                                            onError: () => {
                                                setConfirm(false);
                                                setError(
                                                    'Não foi possível publicar o comunicado.',
                                                );
                                            },
                                            onHttpException: () => {
                                                setConfirm(false);
                                                setError(
                                                    'Não foi possível publicar o comunicado. Tente novamente.',
                                                );

                                                return false;
                                            },
                                            onNetworkError: () => {
                                                setConfirm(false);
                                                setError(
                                                    'Não foi possível conectar ao servidor.',
                                                );

                                                return false;
                                            },
                                            onFinish: () => {
                                                lock.current = false;
                                            },
                                        },
                                    );
                                }}
                            >
                                {publication.processing
                                    ? 'Publicando...'
                                    : 'Confirmar publicação'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

AnnouncementForm.layout = {
    breadcrumbs: [{ title: 'Comunicados', href: index() }],
};
