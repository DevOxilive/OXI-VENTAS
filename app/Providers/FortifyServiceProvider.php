<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        Fortify::loginView(function () {
            return Inertia::render('Auth/Login', [
                'branches' => Branch::where('active', true)
                    ->orderBy('name')
                    ->get(),
            ]);
        });

        Fortify::authenticateUsing(function (Request $request) {
            $request->validate([
                'email' => ['required', 'string'],
                'password' => ['required', 'string'],
                'branch_id' => ['nullable', 'exists:branches,id'],
            ]);

            $user = User::with('role')
                ->where('email', $request->email)
                ->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'email' => __('auth.failed'),
                ]);
            }

            $role = $user->role?->name;

            if ($role === 'Ventas') {
                if (! $request->branch_id) {
                    throw ValidationException::withMessages([
                        'branch_id' => 'Debes seleccionar tu sucursal.',
                    ]);
                }

                $canEnter = $user->branches()
                    ->where('branches.id', $request->branch_id)
                    ->exists();

                if (! $canEnter) {
                    throw ValidationException::withMessages([
                        'branch_id' => 'No tienes acceso a la sucursal seleccionada.',
                    ]);
                }

                session([
                    'branch_id' => $request->branch_id,
                ]);
            } else {
                session([
                    'branch_id' => null,
                ]);
            }

            return $user;
        });

        $this->app->singleton(RegisterResponse::class, function () {
            return new class implements RegisterResponse {
                public function toResponse($request)
                {
                    Auth::logout();

                    return redirect()->route('register')
                        ->with('success', 'Usuario registrado correctamente');
                }
            };
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(
                Str::lower($request->input(Fortify::username())) . '|' . $request->ip()
            );

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}