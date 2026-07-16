<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */


    public function share(Request $request): array
    {
        $user = $request->user()?->loadMissing(['role', 'permissions', 'branches']);

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user
                    ? $user
                    : null,

               'permissions' => $user
    ? $user->all_permissions->pluck('name')->values()
    : [],
            ],

            'branches' => fn () => $user
                ? $user->accessibleBranchesQuery()
                    ->select('branches.id', 'branches.name', 'branches.slug', 'branches.color')
                    ->orderBy('branches.name')
                    ->get()
                : collect(),

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'sale_folio' => fn () => $request->session()->get('sale_folio'),
                'print_job' => fn () => $request->session()->get('print_job'),
                'cash_closure_print_jobs' => fn () => $request->session()->get('cash_closure_print_jobs', []),
                'expiration_alerts' => fn () => $request->session()->get('expiration_alerts', []),
            ],
        ]);
    }
}
