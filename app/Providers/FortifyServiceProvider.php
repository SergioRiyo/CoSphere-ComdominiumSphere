<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()
                ->where('email', $request->string(Fortify::username())->toString())
                ->first();

            if ($user === null || ! $user->is_active || ! Hash::check($request->string('password')->toString(), $user->password)) {
                return null;
            }

            if (Hash::needsRehash($user->password)) {
                $user->forceFill([
                    'password' => Hash::make($request->string('password')->toString()),
                ])->save();
            }

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register'));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
        RateLimiter::for('visitor-invitation', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('visitor-authorization', fn (Request $request) => Limit::perMinute(10)->by($this->authenticatedThrottleKey($request)));
        RateLimiter::for('visitor-qr-code', fn (Request $request) => Limit::perMinute(20)->by($this->authenticatedThrottleKey($request)));
        RateLimiter::for('visitor-portaria-validation', fn (Request $request) => Limit::perMinute(30)->by($this->authenticatedThrottleKey($request)));
        RateLimiter::for('visitor-portaria-entry', fn (Request $request) => Limit::perMinute(30)->by($this->authenticatedThrottleKey($request)));
        RateLimiter::for('visitor-portaria-exit', fn (Request $request) => Limit::perMinute(30)->by($this->authenticatedThrottleKey($request)));
    }

    private function authenticatedThrottleKey(Request $request): string
    {
        return ($request->user()?->getAuthIdentifier() ?? 'guest').'|'.$request->ip();
    }
}
