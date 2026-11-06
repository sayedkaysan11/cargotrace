<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;

trait ResolvesPerPage
{
    /** ?per_page=… clamped to 1..100 so nobody can request the whole table at once. */
    protected function perPage(Request $request, int $default = 15): int
    {
        return min(max((int) $request->input('per_page', $default), 1), 100);
    }
}
