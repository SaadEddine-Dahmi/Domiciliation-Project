<?php
// app/Http/Controllers/Api/DashboardController.php
// Returns role-specific dashboard metrics for the authenticated user.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardStatsService;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardStatsService $stats)
    {
    }

    public function stats()
    {
        return response()->json([
            'success' => true,
            'data' => $this->stats->forUser(auth()->user()),
        ]);
    }
}
