<?php
// app/Services/ActivationService.php
// Manages admin approval state changes for tenant accounts.

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActivationService
{
    public function approve(User $user, string $activationDate): void
    {
        DB::transaction(fn() => $user->update([
            'status' => 'approved',
            'activation_date' => $activationDate,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'rejection_reason' => null,
        ]));
    }

    public function reject(User $user, string $reason): void
    {
        DB::transaction(fn() => $user->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'activation_date' => null,
        ]));
    }

    public function activateIfReady(User $user): void
    {
        if (
            $user->status === 'approved'
            && $user->activation_date !== null
            && now()->greaterThanOrEqualTo($user->activation_date)
        ) {
            DB::transaction(fn() => $user->update(['status' => 'active']));
        }
    }
}
