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

            <main className="dark h-svh overflow-hidden bg-cosphere-deep lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(27rem,0.9fr)] lg:grid-rows-[minmax(0,1fr)] lg:p-3 xl:p-4">
                <div className="hidden min-h-0 overflow-hidden lg:block">
                    <AuthLoginShowcase />
                </div>

                <section className="relative flex h-svh min-h-0 items-center bg-cosphere-surface px-5 py-3 sm:px-6 lg:h-auto lg:rounded-[1.75rem] lg:px-8 xl:px-12">
                    <div className="mx-auto w-full max-w-sm">
                        <Link
                            href={home()}
                            className="inline-flex items-center gap-1.5 text-sm font-medium text-cosphere-muted transition-colors hover:text-cosphere-navy focus-visible:ring-2 focus-visible:ring-cosphere-blue focus-visible:ring-offset-2 focus-visible:outline-none lg:absolute lg:top-16 lg:left-1/2 lg:w-full lg:max-w-sm lg:-translate-x-1/2 dark:hover:text-foreground"
                        >
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Início
                        </Link>

                        <div className="mt-3 text-center">
                            <Link
                                href={home()}
                                className="inline-flex"
                                aria-label="CoSphere — página inicial"
                            >
                                <CoSphereLogo
                                    size="lg"
                                    withTagline
                                    className="size-72"
                                />
                            </Link>
                            <h1 className="mt-2 text-xl font-semibold tracking-tight text-cosphere-navy dark:text-foreground">
                                Bem-vindo ao CoSphere
                            </h1>
                            <p className="mt-1 text-xs text-cosphere-muted sm:text-sm">
                                Acesse o sistema de gestão condominial.
                            </p>
                            <p className="mt-2 inline-flex items-center gap-2 text-xs font-medium text-cosphere-blue sm:text-sm">
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
                            className="mt-3"
                        >
                            {({ processing, errors }) => (
                                <div className="grid gap-3">
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor="email"
                                            className="text-cosphere-navy dark:text-foreground"
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
                                                className="h-10 rounded-xl border-cosphere-line bg-card pl-11 text-foreground shadow-sm placeholder:text-cosphere-muted focus-visible:border-cosphere-blue focus-visible:ring-cosphere-blue/25"
                                            />
                                        </div>
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor="password"
                                            className="text-cosphere-navy dark:text-foreground"
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
                                                className="h-10 rounded-xl border-cosphere-line bg-card pl-11 text-foreground shadow-sm placeholder:text-cosphere-muted focus-visible:border-cosphere-blue focus-visible:ring-cosphere-blue/25"
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
                                        className="mt-1 h-10 w-full rounded-xl bg-cosphere-orange text-sm text-white shadow-cosphere-soft hover:bg-cosphere-orange/90"
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
                            <div className="mt-3">
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
                                <div className="mt-2 text-center">
                                    <TextLink
                                        href={request()}
                                        tabIndex={5}
                                        className="inline-flex items-center gap-2 text-sm font-medium text-cosphere-blue no-underline hover:text-cosphere-navy dark:hover:text-foreground"
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
