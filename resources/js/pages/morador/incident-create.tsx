import { Head, Link, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { IncidentSelect } from '@/components/incident-fields';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, store } from '@/routes/morador/incidents';
import type { IncidentOption } from '@/types/incident';

export default function IncidentCreate({
    options,
}: {
    options: { types: IncidentOption[]; categories: IncidentOption[] };
}) {
    const submitting = useRef(false);
    const [fileErrors, setFileErrors] = useState<string[]>([]);
    const form = useForm({
        type: 'incident',
        title: '',
        category: '',
        description: '',
        attachments: [] as File[],
    });
    function selectFiles(files: File[]) {
        form.setData('attachments', files);
        form.clearErrors();
        setFileErrors(
            files.flatMap((file) => {
                if (file.size > 10 * 1024 * 1024) {
                    return [file.name + ': o limite é 10 MiB por arquivo.'];
                }

                if (!/\.(jpe?g|png|gif|bmp|webp|pdf)$/i.test(file.name)) {
                    return [file.name + ': formato não permitido.'];
                }

                return [];
            }),
        );
    }
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (submitting.current || fileErrors.length > 0) {
            return;
        }

        submitting.current = true;
        form.post(store.url(), {
            forceFormData: true,
            onSuccess: () =>
                toast.success('Solicitação registrada com sucesso.'),
            onError: () =>
                toast.error('Confira os campos e os anexos da solicitação.'),
            onFinish: () => {
                submitting.current = false;
            },
        });
    }

    return (
        <>
            <Head title="Nova solicitação" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="grid gap-2">
                    <h1 className="text-2xl font-semibold">Nova solicitação</h1>
                    <p className="text-sm text-muted-foreground">
                        Registre uma ocorrência ou solicite manutenção à
                        administração.
                    </p>
                </header>
                <form
                    onSubmit={submit}
                    className="grid w-full max-w-3xl gap-5 rounded-xl border p-4 sm:p-6"
                >
                    <fieldset
                        disabled={form.processing}
                        className="grid min-w-0 gap-5"
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <IncidentSelect
                                id="incident-type"
                                label="Tipo"
                                value={form.data.type}
                                options={options.types}
                                onChange={(value) =>
                                    form.setData('type', value)
                                }
                                error={form.errors.type}
                                disabled={form.processing}
                            />
                            <IncidentSelect
                                id="incident-category"
                                label="Categoria"
                                value={form.data.category}
                                options={options.categories}
                                onChange={(value) =>
                                    form.setData('category', value)
                                }
                                error={form.errors.category}
                                disabled={form.processing}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="incident-title">Título</Label>
                            <Input
                                id="incident-title"
                                required
                                maxLength={255}
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                            />
                            <InputError message={form.errors.title} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="incident-description">
                                Descrição
                            </Label>
                            <textarea
                                id="incident-description"
                                required
                                maxLength={10000}
                                rows={6}
                                className="w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring"
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.description} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="incident-files">
                                Anexos (opcional)
                            </Label>
                            <Input
                                id="incident-files"
                                type="file"
                                multiple
                                accept=".jpg,.jpeg,.png,.gif,.bmp,.webp,.pdf"
                                onChange={(event) =>
                                    selectFiles(
                                        Array.from(event.target.files || []),
                                    )
                                }
                                aria-describedby="incident-file-help"
                            />
                            <p
                                id="incident-file-help"
                                className="text-sm text-muted-foreground"
                            >
                                JPG, PNG, GIF, BMP, WebP ou PDF. Até 10 MiB por
                                arquivo. Os anexos são privados.
                            </p>
                            {form.data.attachments.length > 0 && (
                                <ul className="grid gap-1 text-sm">
                                    {form.data.attachments.map(
                                        (file, index) => (
                                            <li
                                                key={index}
                                                className="break-all"
                                            >
                                                {file.name} (
                                                {(
                                                    file.size /
                                                    1024 /
                                                    1024
                                                ).toFixed(2)}{' '}
                                                MiB)
                                            </li>
                                        ),
                                    )}
                                </ul>
                            )}
                            {fileErrors.map((message) => (
                                <InputError key={message} message={message} />
                            ))}
                            {Object.entries(form.errors)
                                .filter(([key]) =>
                                    key.startsWith('attachments'),
                                )
                                .map(([key, message]) => (
                                    <InputError key={key} message={message} />
                                ))}
                        </div>
                    </fieldset>
                    {form.progress && (
                        <div role="status" className="grid gap-2 text-sm">
                            <span>Enviando: {form.progress.percentage}%</span>
                            <progress
                                className="w-full"
                                max={100}
                                value={form.progress.percentage}
                            />
                        </div>
                    )}
                    <div className="flex flex-wrap gap-3">
                        <Button
                            type="submit"
                            disabled={form.processing || fileErrors.length > 0}
                        >
                            {form.processing
                                ? 'Registrando...'
                                : 'Registrar solicitação'}
                        </Button>
                        <Button
                            variant="outline"
                            asChild
                            disabled={form.processing}
                        >
                            <Link href={index()}>Voltar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
IncidentCreate.layout = {
    breadcrumbs: [
        { title: 'Solicitações', href: index() },
        { title: 'Nova solicitação' },
    ],
};
