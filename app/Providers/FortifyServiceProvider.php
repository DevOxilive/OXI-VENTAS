<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\Sucursal;
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
                'sucursales' => Sucursal::where('activa', true)
                    ->orderBy('nombre')
                    ->get(),
            ]);
        });
Fortify::authenticateUsing(function (Request $request) {

    $request->validate([
        'email' => ['required', 'string'],
        'password' => ['required', 'string'],
        'sucursal_id' => ['nullable', 'exists:sucursales,id'],
    ]);

    $user = User::with('role')
        ->where('email', $request->email)
        ->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => __('auth.failed'),
        ]);
    }

    $rol = $user->role?->name;

    if ($rol === 'Ventas') {

    if (! $request->sucursal_id) {
        throw ValidationException::withMessages([
            'sucursal_id' => 'Debes seleccionar tu sucursal.',
        ]);
    }

    $puedeEntrar = $user->sucursales()
        ->where('sucursales.id', $request->sucursal_id)
        ->exists();

    if (! $puedeEntrar) {
        throw ValidationException::withMessages([
            'sucursal_id' => 'No tienes acceso a la sucursal seleccionada.',
        ]);
    }

    session([
        'sucursal_id' => $request->sucursal_id,
    ]);
} else {
    session([
        'sucursal_id' => null,
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