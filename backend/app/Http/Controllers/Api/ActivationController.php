<?php
// app/Http/Controllers/Api/ActivationController.php
// Handles admin approval and rejection of pending tenant accounts.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesApiRoles;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivationService;
use Illuminate\Http\Request;

class ActivationController extends Controller
{
    use AuthorizesApiRoles;

    public function __construct(private readonly ActivationService $service)
    {
    }

    public function pending()
    {
        if ($blocked = $this->denyUnlessRole(auth()->user(), 'admin')) {
            return $blocked;
        }

        $users = User::where('status', 'pending')
            ->where('role', 'domiciliataire')
            ->latest()
            ->get(['id', 'nom', 'prenom', 'email', 'telephone', 'status', 'activation_date', 'created_at']);

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function approve(Request $request, int $id)
    {
        if ($blocked = $this->denyUnlessRole(auth()->user(), 'admin')) {
            return $blocked;
        }

        $data = $request->validate([
            'activation_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $user = User::findOrFail($id);
        if ($blocked = $this->denyInvalidPendingAccount($user, 'approuvé')) {
            return $blocked;
        }

        $this->service->approve($user, $data['activation_date']);

        return response()->json(['success' => true, 'message' => 'Compte approuvé.']);
    }

    public function reject(Request $request, int $id)
    {
        if ($blocked = $this->denyUnlessRole(auth()->user(), 'admin')) {
            return $blocked;
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $user = User::findOrFail($id);
        if ($blocked = $this->denyInvalidPendingAccount($user, 'rejeté')) {
            return $blocked;
        }

        $this->service->reject($user, $data['reason']);

        return response()->json(['success' => true, 'message' => 'Compte rejeté.']);
    }

    private function denyInvalidPendingAccount(User $user, string $action): ?\Illuminate\Http\JsonResponse
    {
        if ($user->status === 'pending' && $user->role === 'domiciliataire') {
            return null;
        }

        return response()->json([
            'message' => "Ce compte ne peut pas être {$action}. Statut actuel : {$user->status}",
        ], 422);
    }
}
