<?php

namespace App\Support;

use Illuminate\Http\Request;

class TablePagination
{
    /**
     * @param  array<int>  $allowed
     */
    public static function resolvePerPage(
        Request $request,
        int $default = 50,
        array $allowed = [10, 25, 50, 100]
    ): int {
        $perPage = (int) $request->input('per_page', $default);

        if (! in_array($perPage, $allowed, true)) {
            return $default;
        }

        return $perPage;
    }
}
