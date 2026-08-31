<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

trait UsesApiPagination
{
    private function perPage(Request $request, int $default = 50, int $max = 100): int
    {
        $value = (int) $request->query('per_page', $default);

        return max(1, min($value, $max));
    }

    private function paginatedResponse(LengthAwarePaginator $paginator): array
    {
        return [
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
