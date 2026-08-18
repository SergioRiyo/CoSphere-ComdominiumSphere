import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Building2, CircleCheck, IdCard, ShieldCheck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { UnitSummary } from '@/types';

type ProfileProps = {
    mustVerifyEmail: boolean;
    profile: {
        name: string;
        email: string;
        phone: string | null;
        cpf: string | null;
        is_active: boolean;
    };
    roleLabel: string;
    status?: string;
    unit: UnitSummary | null;
};

type ProfileDetailProps = {
    icon: LucideIcon;
    label: string;
    value: ReactNode;
};

function ProfileDetail({ icon: Icon, label, value }: ProfileDetailProps) {
    return (
        <div className="flex min-w-0 gap-3 rounded-xl border border-cosphere-line/70 bg-cosphere-surface/60 p-4 dark:border-border dark:bg-muted/20">
            <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-lg bg-cosphere-blue/10 text-cosphere-blue">
                <Icon className="size-5" aria-hidden="true" />
            </span>
            <div className="min-w-0">
                <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </dt>
                <dd className="mt-1 text-sm font-medium break-words">
                    {value}
                </dd>
            </div>
        </div>
    );
}

function formatUnit(unit: UnitSummary | null): string {
    if (unit === null) {
        return 'Não vinculada';
    }

    return [
        unit.block ? `Bloco ${unit.block}` : null,
        `Unidade ${unit.number}`,
        unit.complement,
    ]
        .filter(Boolean)
        .join(' · ');
}

export default function Profile({
    mustVerifyEmail,
    profile,
    roleLabel,
    status,
    unit,
}: ProfileProps) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Meu perfil" />

            <h1 className="sr-only">Meu perfil</h1>

            <div className="space-y-6">
                <Heading
                    title="Meu perfil"
                    description="Consulte seus dados cadastrais e mantenha suas informações de contato atualizadas."
                />

                <Card className="border-cosphere-line/80 shadow-cosphere-soft dark:border-border">
                    <CardHeader>
                        <h2 className="text-lg font-semibold text-cosphere-navy dark:text-foreground">
                            Informações pessoais
                        </h2>
                        <CardDescription>
                            Você pode alterar seu nome, e-mail e telefone.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...ProfileController.update.form()}
                            options={{ preserveScroll: true }}
                            setDefaultsOnSuccess
                            className="space-y-5"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Nome</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={profile.name}
                                            required
                                            autoComplete="name"
                                            placeholder="Nome completo"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="email">E-mail</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            name="email"
                                            defaultValue={profile.email}
                                            required
                                            autoComplete="email"
                                            placeholder="seu.email@exemplo.com"
                                        />
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="phone">Telefone</Label>
                                        <Input
                                            id="phone"
                                            type="tel"
                                            name="phone"
                                            defaultValue={profile.phone ?? ''}
                                            autoComplete="tel"
                                            placeholder="(00) 00000-0000"
                                        />
                                        <InputError message={errors.phone} />
                                    </div>

                                    {mustVerifyEmail &&
                                        auth.user.email_verified_at ===
                                            null && (
                                            <div className="rounded-lg border border-cosphere-orange/30 bg-cosphere-orange/5 p-4 text-sm text-muted-foreground">
                                                <p>
                                                    Seu endereço de e-mail ainda
                                                    não foi verificado.{' '}
                                                    <Link
                                                        href={send()}
                                                        as="button"
                                                        className="font-medium text-cosphere-blue underline underline-offset-4"
                                                    >
                                                        Reenviar e-mail de
                                                        verificação
                                                    </Link>
                                                </p>

                                                {status ===
                                                    'verification-link-sent' && (
                                                    <p className="mt-2 font-medium text-emerald-600 dark:text-emerald-400">
                                                        Um novo link de
                                                        verificação foi enviado.
                                                    </p>
                                                )}
                                            </div>
                                        )}

                                    <Button
                                        disabled={processing}
                                        data-test="update-profile-button"
                                    >
                                        Salvar alterações
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card className="border-cosphere-line/80 shadow-sm dark:border-border">
                    <CardHeader>
                        <h2 className="text-lg font-semibold text-cosphere-navy dark:text-foreground">
                            Dados do cadastro
                        </h2>
                        <CardDescription>
                            Estes dados são administrados pelo condomínio e não
                            podem ser alterados nesta página.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-3 sm:grid-cols-2">
                            <ProfileDetail
                                icon={IdCard}
                                label="CPF"
                                value={profile.cpf ?? 'Não informado'}
                            />
                            <ProfileDetail
                                icon={ShieldCheck}
                                label="Perfil"
                                value={
                                    <Badge variant="secondary">
                                        {roleLabel}
                                    </Badge>
                                }
                            />
                            <ProfileDetail
                                icon={CircleCheck}
                                label="Status"
                                value={
                                    <Badge
                                        variant="outline"
                                        className={
                                            profile.is_active
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300'
                                                : 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/50 dark:text-red-300'
                                        }
                                    >
                                        {profile.is_active
                                            ? 'Ativo'
                                            : 'Inativo'}
                                    </Badge>
                                }
                            />
                            <ProfileDetail
                                icon={Building2}
                                label="Unidade"
                                value={formatUnit(unit)}
                            />
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Meu perfil',
            href: edit(),
        },
    ],
};
