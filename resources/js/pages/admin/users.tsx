import { Form, Head, Link, router } from '@inertiajs/react';
import { Pencil, Search, UserCheck, UserPlus, UserX, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { updateStatus } from '@/actions/App/Http/Controllers/UserController';
import UserFormDialog from '@/components/admin/user-form-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/admin/users';
import type {
    ManagedUser,
    PaginatedUsers,
    UnitOption,
    UserFilters,
    UserRoleOption,
} from '@/types';

type UsersPageProps = {
    users: PaginatedUsers;
    units: UnitOption[];
    roleOptions: UserRoleOption[];
    filters: UserFilters;
};

export default function UsersPage({
    users,
    units,
    roleOptions,
    filters,
}: UsersPageProps) {
    const [search, setSearch] = useState(filters.search);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<ManagedUser | null>(null);

    const visitWithFilters = (nextFilters: UserFilters) => {
        router.get(
            index.url(),
            {
                search: nextFilters.search || undefined,
                role: nextFilters.role || undefined,
                status: nextFilters.status || undefined,
            },
            {
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        visitWithFilters({ ...filters, search });
    };

    const clearFilters = () => {
        setSearch('');
        visitWithFilters({ search: '', role: '', status: '' });
    };

    const openCreateDialog = () => {
        setEditingUser(null);
        setDialogOpen(true);
    };

    const openEditDialog = (user: ManagedUser) => {
        setEditingUser(user);
        setDialogOpen(true);
    };

    const hasFilters = Boolean(
        filters.search || filters.role || filters.status,
    );

    return (
        <>
            <Head title="Gestão de usuários" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Gestão de usuários
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Cadastre usuários, defina perfis, unidades e status
                            de acesso.
                        </p>
                    </div>

                    <Button onClick={openCreateDialog}>
                        <UserPlus />
                        Novo usuário
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Pesquisa e filtros</CardTitle>
                        <CardDescription>
                            Pesquise por nome, CPF ou e-mail e combine os
                            filtros disponíveis.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submitSearch}
                            className="grid gap-3 lg:grid-cols-[minmax(16rem,1fr)_12rem_12rem_auto]"
                        >
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    className="pl-9"
                                    placeholder="Nome, CPF ou e-mail"
                                    aria-label="Pesquisar usuários"
                                />
                            </div>

                            <Select
                                value={filters.role || 'all'}
                                onValueChange={(role) =>
                                    visitWithFilters({
                                        ...filters,
                                        role:
                                            role === 'all'
                                                ? ''
                                                : (role as UserFilters['role']),
                                    })
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Todos os perfis" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Todos os perfis
                                    </SelectItem>
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

                            <Select
                                value={filters.status || 'all'}
                                onValueChange={(status) =>
                                    visitWithFilters({
                                        ...filters,
                                        status:
                                            status === 'all'
                                                ? ''
                                                : (status as UserFilters['status']),
                                    })
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Todos os status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Todos os status
                                    </SelectItem>
                                    <SelectItem value="active">
                                        Ativos
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        Inativos
                                    </SelectItem>
                                </SelectContent>
                            </Select>

                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    className="flex-1 lg:flex-none"
                                >
                                    Pesquisar
                                </Button>
                                {hasFilters && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        onClick={clearFilters}
                                        aria-label="Limpar filtros"
                                    >
                                        <X />
                                    </Button>
                                )}
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card className="gap-0 py-0">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[760px] text-sm">
                            <thead className="border-b bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-5 py-3">Usuário</th>
                                    <th className="px-5 py-3">Unidade</th>
                                    <th className="px-5 py-3">Perfil</th>
                                    <th className="px-5 py-3">Status</th>
                                    <th className="px-5 py-3 text-right">
                                        Ações
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {users.data.map((user) => (
                                    <tr
                                        key={user.id}
                                        className="transition-colors hover:bg-muted/30"
                                    >
                                        <td className="px-5 py-4">
                                            <div className="font-medium">
                                                {user.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {user.email} · {user.cpf}
                                            </div>
                                        </td>
                                        <td className="px-5 py-4 text-muted-foreground">
                                            {user.unit
                                                ? user.unit.block
                                                    ? `Bloco ${user.unit.block} · Unidade ${user.unit.number}`
                                                    : `Unidade ${user.unit.number}`
                                                : '—'}
                                        </td>
                                        <td className="px-5 py-4">
                                            <Badge variant="secondary">
                                                {user.role_label}
                                            </Badge>
                                        </td>
                                        <td className="px-5 py-4">
                                            <Badge
                                                variant="outline"
                                                className={
                                                    user.is_active
                                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300'
                                                        : 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/50 dark:text-red-300'
                                                }
                                            >
                                                {user.is_active
                                                    ? 'Ativo'
                                                    : 'Inativo'}
                                            </Badge>
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        openEditDialog(user)
                                                    }
                                                >
                                                    <Pencil />
                                                    Editar
                                                </Button>

                                                <Form
                                                    {...updateStatus.form(
                                                        user.id,
                                                    )}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                >
                                                    {({ processing }) => (
                                                        <>
                                                            <input
                                                                type="hidden"
                                                                name="is_active"
                                                                value={
                                                                    user.is_active
                                                                        ? '0'
                                                                        : '1'
                                                                }
                                                            />
                                                            <Button
                                                                type="submit"
                                                                variant={
                                                                    user.is_active
                                                                        ? 'outline'
                                                                        : 'default'
                                                                }
                                                                size="sm"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                {user.is_active ? (
                                                                    <UserX />
                                                                ) : (
                                                                    <UserCheck />
                                                                )}
                                                                {user.is_active
                                                                    ? 'Inativar'
                                                                    : 'Ativar'}
                                                            </Button>
                                                        </>
                                                    )}
                                                </Form>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {users.data.length === 0 && (
                        <div className="px-6 py-12 text-center">
                            <p className="font-medium">
                                Nenhum usuário encontrado
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Ajuste os filtros ou cadastre um novo usuário.
                            </p>
                        </div>
                    )}

                    <div className="flex flex-col gap-3 border-t px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-sm text-muted-foreground">
                            {users.total === 0
                                ? 'Nenhum resultado'
                                : `Mostrando ${users.from}–${users.to} de ${users.total}`}
                        </p>

                        {users.last_page > 1 && (
                            <nav
                                className="flex flex-wrap items-center gap-1"
                                aria-label="Paginação de usuários"
                            >
                                <PaginationLink
                                    href={users.prev_page_url}
                                    label="Anterior"
                                />

                                {users.links.slice(1, -1).map((link, index) => (
                                    <PaginationLink
                                        key={`${link.label}-${index}`}
                                        href={link.url}
                                        label={link.label}
                                        active={link.active}
                                        compact
                                    />
                                ))}

                                <PaginationLink
                                    href={users.next_page_url}
                                    label="Próxima"
                                />
                            </nav>
                        )}
                    </div>
                </Card>
            </div>

            <UserFormDialog
                key={`${editingUser?.id ?? 'create'}-${dialogOpen ? 'open' : 'closed'}`}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                user={editingUser}
                units={units}
                roleOptions={roleOptions}
            />
        </>
    );
}

function PaginationLink({
    href,
    label,
    active = false,
    compact = false,
}: {
    href: string | null;
    label: string;
    active?: boolean;
    compact?: boolean;
}) {
    if (href === null) {
        return (
            <Button
                type="button"
                variant="outline"
                size={compact ? 'icon' : 'sm'}
                disabled
            >
                {label}
            </Button>
        );
    }

    return (
        <Button
            variant={active ? 'default' : 'outline'}
            size={compact ? 'icon' : 'sm'}
            asChild
        >
            <Link href={href} preserveScroll>
                {label}
            </Link>
        </Button>
    );
}

UsersPage.layout = {
    breadcrumbs: [
        {
            title: 'Gestão de usuários',
            href: index(),
        },
    ],
};
