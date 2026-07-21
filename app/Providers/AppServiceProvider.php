<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Failed;
use App\Services\SystemAuditService;
use App\Actions\GeneratePlatformPasskeyRegistrationOptions;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use App\Observers\SystemAuditObserver;
use App\Models\{CashRegisterClosure, PhysicalCount, PhysicalCountEntry};
use App\Support\TrashRegistry;
use Illuminate\Support\Facades\Vite;
use Inertia\Inertia;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GenerateRegistrationOptions::class, GeneratePlatformPasskeyRegistrationOptions::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::createAssetPathsUsing(fn (string $path) => "/{$path}");

        Inertia::share([
            'auth.user' => function () {
                $user = Auth::user();

                if (!$user) return null;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ];
            }
        ]);

        $audit = app(SystemAuditService::class);

        $observer = app(SystemAuditObserver::class);
        foreach ([...TrashRegistry::modelClasses(), CashRegisterClosure::class, PhysicalCount::class, PhysicalCountEntry::class] as $model) {
            $model::observe($observer);
        }

        \Illuminate\Support\Facades\Event::listen(Login::class, function (Login $event) use ($audit) {
            $audit->record('authentication', 'login', 'success', request(), ['actor' => $event->user]);
        });

        \Illuminate\Support\Facades\Event::listen(Logout::class, function (Logout $event) use ($audit) {
            $audit->record('authentication', 'logout', 'success', request(), ['actor' => $event->user]);
        });

        \Illuminate\Support\Facades\Event::listen(Failed::class, function (Failed $event) use ($audit) {
            $audit->record('authentication', 'login', 'error', request(), [
                'observations' => 'Credenciales inválidas.',
                'metadata' => ['email' => $event->credentials['email'] ?? null],
            ]);
        });
    }
}
