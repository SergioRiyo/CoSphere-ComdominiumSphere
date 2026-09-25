import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
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
import { store, update } from '@/routes/admin/common-areas';
import type { CommonArea, CommonAreaStatus } from '@/types/common-area';

export const commonAreaStatusLabels: Record<CommonAreaStatus, string> = {
    active: 'Ativa',
    inactive: 'Inativa',
    maintenance: 'Manutenção',
};

type CommonAreaFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    area: CommonArea | null;
};

const textareaClassName =
    'min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive dark:bg-input/30 md:text-sm';

export default function CommonAreaFormDialog({
    open,
    onOpenChange,
    area,
}: CommonAreaFormDialogProps) {
    const [status, setStatus] = useState<CommonAreaStatus>(
        area?.status ?? 'active',
    );
    const [approval, setApproval] = useState(
        area?.requires_approval === false ? '0' : '1',
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {area ? 'Editar área comum' : 'Nova área comum'}
                    </DialogTitle>
                    <DialogDescription>
                        Configure as regras da área. Alterações não modificam
                        reservas existentes.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={area ? update(area.id) : store()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <fieldset
                                disabled={processing}
                                className="grid min-w-0 gap-4 sm:grid-cols-2"
                            >
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="area-name">Nome</Label>
                                    <Input
                                        id="area-name"
                                        name="name"
                                        defaultValue={area?.name ?? ''}
                                        required
                                        maxLength={255}
                                        aria-invalid={Boolean(errors.name)}
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="area-description">
                                        Descrição (opcional)
                                    </Label>
                                    <textarea
                                        id="area-description"
                                        name="description"
                                        defaultValue={area?.description ?? ''}
                                        className={textareaClassName}
                                        aria-invalid={Boolean(
                                            errors.description,
                                        )}
                                    />
                                    <InputError message={errors.description} />
                                </div>
                                <p
                                    id="area-hours-help"
                                    className="text-sm text-muted-foreground sm:col-span-2"
                                >
                                    Informe abertura e fechamento no mesmo dia,
                                    ou deixe ambos vazios para não definir uma
                                    faixa de funcionamento.
                                </p>
                                <div className="grid gap-2">
                                    <Label htmlFor="area-opening">
                                        Abertura
                                    </Label>
                                    <Input
                                        id="area-opening"
                                        name="available_from"
                                        type="time"
                                        step={60}
                                        defaultValue={
                                            area?.available_from?.slice(0, 5) ??
                                            ''
                                        }
                                        aria-describedby="area-hours-help"
                                        aria-invalid={Boolean(
                                            errors.available_from,
                                        )}
                                    />
                                    <InputError
                                        message={errors.available_from}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="area-closing">
                                        Fechamento
                                    </Label>
                                    <Input
                                        id="area-closing"
                                        name="available_until"
                                        type="time"
                                        step={60}
                                        defaultValue={
                                            area?.available_until?.slice(
                                                0,
                                                5,
                                            ) ?? ''
                                        }
                                        aria-describedby="area-hours-help"
                                        aria-invalid={Boolean(
                                            errors.available_until,
                                        )}
                                    />
                                    <InputError
                                        message={errors.available_until}
                                    />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="area-duration">
                                        Duração máxima da reserva (minutos)
                                    </Label>
                                    <Input
                                        id="area-duration"
                                        name="max_reservation_minutes"
                                        type="number"
                                        min={1}
                                        max={32767}
                                        step={1}
                                        required
                                        defaultValue={
                                            area?.max_reservation_minutes ?? ''
                                        }
                                        aria-invalid={Boolean(
                                            errors.max_reservation_minutes,
                                        )}
                                    />
                                    <InputError
                                        message={errors.max_reservation_minutes}
                                    />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="area-rules">
                                        Regras de utilização
                                    </Label>
                                    <textarea
                                        id="area-rules"
                                        name="rules"
                                        required
                                        defaultValue={area?.rules ?? ''}
                                        className={textareaClassName}
                                        aria-invalid={Boolean(errors.rules)}
                                    />
                                    <InputError message={errors.rules} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="area-approval">
                                        Tipo de aprovação
                                    </Label>
                                    <input
                                        type="hidden"
                                        name="requires_approval"
                                        value={approval}
                                    />
                                    <Select
                                        value={approval}
                                        onValueChange={setApproval}
                                        disabled={processing}
                                    >
                                        <SelectTrigger
                                            id="area-approval"
                                            className="w-full"
                                            aria-invalid={Boolean(
                                                errors.requires_approval,
                                            )}
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="0">
                                                Automática
                                            </SelectItem>
                                            <SelectItem value="1">
                                                Pelo administrador
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.requires_approval}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="area-status">Status</Label>
                                    <input
                                        type="hidden"
                                        name="status"
                                        value={status}
                                    />
                                    <Select
                                        value={status}
                                        onValueChange={(value) =>
                                            setStatus(value as CommonAreaStatus)
                                        }
                                        disabled={processing}
                                    >
                                        <SelectTrigger
                                            id="area-status"
                                            className="w-full"
                                            aria-invalid={Boolean(
                                                errors.status,
                                            )}
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(
                                                commonAreaStatusLabels,
                                            ).map(([value, label]) => (
                                                <SelectItem
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.status} />
                                </div>
                            </fieldset>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        disabled={processing}
                                    >
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Salvando...'
                                        : area
                                          ? 'Salvar alterações'
                                          : 'Cadastrar área'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
