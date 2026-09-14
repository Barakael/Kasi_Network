<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\VoucherBatch;

/**
 * Batch permissions, and the boundary that defines the reseller tier.
 *
 * An agent sells printed vouchers at a counter. They may print the batches handed
 * to them and nothing else: not other agents' stock, not batches still being
 * generated, and above all not the ability to issue vouchers, which would let
 * them mint sellable stock the operator never accounted for.
 */
class VoucherBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, VoucherBatch $batch): bool
    {
        if (! $user->isAgent()) {
            return true;
        }

        return $this->isAssignedTo($user, $batch);
    }

    public function create(User $user): bool
    {
        // Issuing stock is an operator decision, never an agent's.
        return $user->managesTenant();
    }

    public function update(User $user, VoucherBatch $batch): bool
    {
        return $user->managesTenant();
    }

    public function delete(User $user, VoucherBatch $batch): bool
    {
        return $user->managesTenant();
    }

    /**
     * Whether the user may render the printable card layout for this batch.
     */
    public function print(User $user, VoucherBatch $batch): bool
    {
        if (! $batch->status->isPrintable()) {
            // Codes for a batch still being generated are incomplete, and a
            // disabled batch would print cards that cannot be redeemed.
            return false;
        }

        if ($user->isAgent()) {
            return $this->isAssignedTo($user, $batch);
        }

        return true;
    }

    /**
     * Withdrawing a batch, for instance when printed stock is reported stolen.
     */
    public function disable(User $user, VoucherBatch $batch): bool
    {
        return $user->managesTenant();
    }

    private function isAssignedTo(User $user, VoucherBatch $batch): bool
    {
        return $batch->assigned_agent_id === $user->id;
    }
}
