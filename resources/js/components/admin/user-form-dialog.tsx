import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { store, update } from '@/actions/App/Http/Controllers/UserController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
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
import type {
    ManagedUser,
    UnitOption,
    UserRole,
    UserRoleOption,
} from '@/types';

type UserFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    user: ManagedUser | null;
    units: UnitOption[];
    roleOptions: UserRoleOption[];
};

function formatCpf(value: string): string {
    return value
        .replace(/\D/g, '')
        .slice(0, 11)
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
}

export default function UserFormDialog({
    open,
    onOpenChange,
    user,
    units,
    roleOptions,
}: UserFormDialogProps) {
    const [role, setRole] = useState<UserRole>(user?.role ?? 'morador');
    const [unitId, setUnitId] = useState(user?.unit?.id.toString() ?? '');
    const [cpf, setCpf] = useState(user?.cpf ?? '');

    const handleRoleChange = (nextRole: UserRole) => {
        setRole(nextRole);

        if (nextRole !== 'morador') {
            setUnitId('');
        }
    };

    const formRoute = user ? update.form(user.id) : store.form();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {user ? 'Editar usuário' : 'Novo usuário'}
                    </DialogTitle>
                    <DialogDescription>
                        {user
                            ? 'Atualize os dados, o perfil e o vínculo com a unidade.'
                            : 'Cadastre um usuário e defina seu perfil de acesso.'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    key={user?.id ?? 'create'}
                    {...formRoute}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="name">Nome</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={user?.name ?? ''}
                                        required
                                        autoComplete="name"
                                        aria-invalid={Boolean(errors.name)}
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="email">E-mail</Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        defaultValue={user?.email ?? ''}
                                        required
                                        autoComplete="email"
                                        aria-invalid={Boolean(errors.email)}
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="cpf">CPF</Label>
                                    <Input
                                        id="cpf"
                                        name="cpf"
                                        value={cpf}
                                        onChange={(event) =>
                                            setCpf(
                                                formatCpf(event.target.value),
                                            )
                                        }
                                        required
                                        inputMode="numeric"
                                        placeholder="000.000.000-00"
                                        maxLength={14}
                                        aria-invalid={Boolean(errors.cpf)}
                                    />
                                    <InputError message={errors.cpf} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Telefone</Label>
                                    <Input
                                        id="phone"
                                        name="phone"
                                        type="tel"
                                        defaultValue={user?.phone ?? ''}
                                        required
                                        maxLength={30}
                                        placeholder="(65) 99999-9999"
                                        autoComplete="tel"
                                        aria-invalid={Boolean(errors.phone)}
                                    />
                                    <InputError message={errors.phone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="role-select">Perfil</Label>
                                    <input
                                        type="hidden"
                                        name="role"
                                        value={role}
                                    />
                                    <Select
                                        value={role}
                                        onValueChange={(value) =>
                                            handleRoleChange(value as UserRole)
                                        }
                                    >
                                        <SelectTrigger
                                            id="role-select"
                                            className="w-full"
                                            aria-invalid={Boolean(errors.role)}
                                        >
                                            <SelectValue placeholder="Selecione o perfil" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {roleOptions.map((option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.role} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="unit-select">
                                        Unidade
                                        {role === 'morador' ? ' *' : ''}
                                    </Label>
                                    <input
                                        type="hidden"
                                        name="unit_id"
                                        value={unitId}
                                    />
                                    <Select
                                        value={unitId || 'none'}
                                        onValueChange={(value) =>
                                            setUnitId(
                                                value === 'none' ? '' : value,
                                            )
                                        }
                                        disabled={role !== 'morador'}
                                    >
                                        <SelectTrigger
                                            id="unit-select"
                                            className="w-full"
                                            aria-invalid={Boolean(
                                                errors.unit_id,
                                            )}
                                        >
                                            <SelectValue
                                                placeholder={
                                                    role === 'morador'
                                                        ? 'Selecione a unidade'
                                                        : 'Não se aplica'
                                                }
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">
                                                Selecione a unidade
                                            </SelectItem>
                                            {units.map((unit) => (
                                                <SelectItem
                                                    key={unit.id}
                                                    value={unit.id.toString()}
                                                >
                                                    {unit.block
                                                        ? `Bloco ${unit.block} · Unidade ${unit.number}`
                                                        : `Unidade ${unit.number}`}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.unit_id} />
                                </div>
                            </div>

                            {!user && (
                                <div className="grid gap-4 border-t pt-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="password">Senha</Label>
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            required
                                            autoComplete="new-password"
                                            aria-invalid={Boolean(
                                                errors.password,
                                            )}
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password_confirmation">
                                            Confirmar senha
                                        </Label>
                                        <PasswordInput
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            required
                                            autoComplete="new-password"
                                        />
                                        <InputError
                                            message={
                                                errors.password_confirmation
                                            }
                                        />
                                    </div>
                                </div>
                            )}

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Salvando...'
                                        : user
                                          ? 'Salvar alterações'
                                          : 'Cadastrar usuário'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
