<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ActivationService
{
    /**
     * Admin approves a pending account.
     * Sets status to 'approved' with a future activation date.
     * Account becomes 'active' on login once that date has passed.
     */
    public function approve(User $user, string $activationDate): void
    {
        $user->update([
            'status'           => 'approved',
            'activation_date'  => $activationDate,
            'approved_by'      => Auth::id(),
            'approved_at'      => now(),
            'rejection_reason' => null,
        ]);
    }

    /**
     * Admin rejects a pending account with a reason.
     */
    public function reject(User $user, string $reason): void
    {
        $user->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
            'approved_by'      => Auth::id(),
            'approved_at'      => now(),
            'activation_date'  => null,
        ]);
    }

    /**
     * Called on every login attempt.
     * If status is 'approved' and activation_date has passed,
     * automatically flip status to 'active'.
     */
    public function activateIfReady(User $user): void
    {
        if (
            $user->status === 'approved' &&
            $user->activation_date !== null &&
            now()->greaterThanOrEqualTo($user->activation_date)
        ) {
            $user->update(['status' => 'active']);
        }
    }
}