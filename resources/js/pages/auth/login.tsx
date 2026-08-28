import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, LockKeyhole, Mail, ShieldCheck } from 'lucide-react';
import AuthLoginShowcase from '@/components/auth-login-showcase';
import CoSphereLogo from '@/components/cosphere-logo';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { home } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Entrar" />

            <main className="min-h-svh bg-cosphere-navy lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(27rem,0.9fr)] lg:p-3 xl:p-4">
                <div className="hidden lg:block">
                    <AuthLoginShowcase />
                </div>

                <section className="flex min-h-svh items-center bg-cosphere-surface px-5 py-6 sm:px-6 lg:min-h-0 lg:rounded-[1.75rem] lg:px-8 xl:px-12">
                    <div className="mx-auto w-full max-w-sm">
                        <Link
                            href={home()}
                            className="inline-flex items-center gap-1.5 text-sm font-medium text-cosphere-muted transition-colors hover:text-cosphere-navy focus-visible:ring-2 focus-visible:ring-cosphere-blue focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Início
                        </Link>

                        <div className="mt-8 text-center sm:mt-10">
                            <Link
                                href={home()}
                                className="inline-flex"
                                aria-label="CoSphere — página inicial"
                            >
                                <CoSphereLogo size="md" withTagline />
                            </Link>
                            <h1 className="mt-6 text-2xl font-semibold tracking-tight text-cosphere-navy">
                                Bem-vindo ao CoSphere
                            </h1>
                            <p className="mt-2 text-sm text-cosphere-muted">
                                Acesse o sistema de gestão condominial.
                            </p>
                            <p className="mt-4 inline-flex items-center gap-2 text-sm font-medium text-cosphere-blue">
                                <ShieldCheck
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Use suas credenciais para continuar.
                            </p>
                        </div>

                        {status ? (
                            <p className="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-700">
                                {status}
                            </p>
                        ) : null}

                        <Form
                            {...store.form()}
                            resetOnSuccess={['password']}
                            className="mt-6"
                        >
                            {({ processing, errors }) => (
                                <div className="grid gap-4">
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor="email"
                                            className="text-cosphere-navy"
                                        >
                                            E-mail
                                        </Label>
                                        <div className="relative">
                                            <Mail
                                                className="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-cosphere-muted"
                                                aria-hidden="true"
                                            />
                                            <Input
                                                id="email"
                                                type="email"
                                                name="email"
                                                required
                                                autoFocus
                                                tabIndex={1}
                                                autoComplete="email"
                                                placeholder="seu.email@condominio.com.br"
                                                className="h-11 rounded-xl border-cosphere-line bg-white pl-11 text-cosphere-navy shadow-sm placeholder:text-cosphere-muted focus-visible:border-cosphere-blue focus-visible:ring-cosphere-blue/25"
                                            />
                                        </div>
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor="password"
                                            className="text-cosphere-navy"
                                        >
                                            Senha
                                        </Label>
                                        <div className="relative">
                                            <LockKeyhole
                                                className="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-cosphere-muted"
                                                aria-hidden="true"
                                            />
                                            <PasswordInput
                                                id="password"
                                                name="password"
                                                required
                                                tabIndex={2}
                                                autoComplete="current-password"
                                                placeholder="••••••••"
                                                className="h-11 rounded-xl border-cosphere-line bg-white pl-11 text-cosphere-navy shadow-sm placeholder:text-cosphere-muted focus-visible:border-cosphere-blue focus-visible:ring-cosphere-blue/25"
                                            />
                                        </div>
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="flex items-center gap-2 pt-0.5">
                                        <Checkbox
                                            id="remember"
                                            name="remember"
                                            tabIndex={3}
                                            className="border-cosphere-muted data-[state=checked]:border-cosphere-blue data-[state=checked]:bg-cosphere-blue"
                                        />
                                        <Label
                                            htmlFor="remember"
                                            className="text-sm font-normal text-cosphere-muted"
                                        >
                                            Manter-me conectado
                                        </Label>
                                    </div>

                                    <Button
                                        type="submit"
                                        tabIndex={4}
                                        disabled={processing}
                                        data-test="login-button"
                                        className="mt-1 h-11 w-full rounded-xl bg-cosphere-orange text-sm text-white shadow-cosphere-soft hover:bg-cosphere-orange/90"
                                    >
                                        {processing ? (
                                            <Spinner
                                                className="text-white"
                                                aria-label="Carregando"
                                            />
                                        ) : null}
                                        Entrar
                                    </Button>
                                </div>
                            )}
                        </Form>

                        {canResetPassword ? (
                            <div className="mt-5">
                                <div
                                    className="flex items-center gap-4"
                                    aria-hidden="true"
                                >
                                    <span className="h-px flex-1 bg-cosphere-line" />
                                    <span className="text-xs text-cosphere-muted">
                                        ou
                                    </span>
                                    <span className="h-px flex-1 bg-cosphere-line" />
                                </div>
                                <div className="mt-4 text-center">
                                    <TextLink
                                        href={request()}
                                        tabIndex={5}
                                        className="inline-flex items-center gap-2 text-sm font-medium text-cosphere-blue no-underline hover:text-cosphere-navy"
                                    >
                                        <LockKeyhole
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Esqueci minha senha
                                    </TextLink>
                                </div>
                            </div>
                        ) : null}
                    </div>
                </section>
            </main>
        </>
    );
}
