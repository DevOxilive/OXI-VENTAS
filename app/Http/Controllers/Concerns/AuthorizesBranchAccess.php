<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;

trait AuthorizesBranchAccess
{
    protected function abortIfUserCannotAccessBranch(Request $request, Branch $branch): void
    {
        /** @var User|null $user */
        $user = $request->user()?->loadMissing(['role', 'branches']);

        abort_unless($user, 401, 'Debes iniciar sesión.');

        abort_unless(
            $user->hasBranchAccess((int) $branch->id),
            403,
            'No tienes acceso a esta sucursal.'
        );
    }

    protected function resolveAccessibleBranch(Request $request, ?string $branchSlug): Branch
    {
        /** @var User|null $user */
        $user = $request->user()?->loadMissing(['role', 'branches']);

        abort_unless($user, 401, 'Debes iniciar sesión.');

        $query = $user->accessibleBranchesQuery()
            ->select('branches.id', 'branches.name', 'branches.slug', 'branches.color')
            ->orderBy('branches.name');

        if (!$branchSlug) {
            return $query->firstOrFail();
        }

        return $query
            ->where('branches.slug', $branchSlug)
            ->firstOrFail();
    }
}
