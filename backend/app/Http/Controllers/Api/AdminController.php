<?php
// app/Http/Controllers/Api/AdminController.php
// Exposes admin-only platform directory endpoints.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesApiRoles;
use App\Http\Controllers\Controller;
use App\Services\AdminDirectoryService;

class AdminController extends Controller
{
    use AuthorizesApiRoles;

    public function __construct(private readonly AdminDirectoryService $directory)
    {
    }

    public function domiciliataires()
    {
        if ($blocked = $this->denyUnlessRole(auth()->user(), 'admin')) {
            return $blocked;
        }

        return response()->json([
            'success' => true,
            'data' => $this->directory->domiciliataires(),
        ]);
    }
}
