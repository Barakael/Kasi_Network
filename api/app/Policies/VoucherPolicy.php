<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Voucher;

class VoucherPolicy
{
    public function viewAny(User $user): bool
    {
        // Agents reach vouchers only through a batch they were assigned, which
        // VoucherBatchPolicy governs.
        return $user->managesTenant();
    }

    public function view(User $user, Voucher $voucher): bool
    {
        return $user->managesTenant();
    }

    public function create(User $user): bool
    {
        return $user->managesTenant();
    }

    public function update(User $user, Voucher $voucher): bool
    {
        return $user->managesTenant();
    }

    /**
     * Withdrawing a single code, for instance one reported as shared publicly.
     */
    public function disable(User $user, Voucher $voucher): bool
    {
        return $user->managesTenant();
    }

    /**
     * Revealing the cleartext code, needed to reprint a damaged card.
     *
     * Held to owners and staff even though agents can print whole batches: a
     * batch print is a physical sheet handed over at once, whereas looking up an
     * individual code is how a code already sold gets read a second time.
     */
    public function reveal(User $user, Voucher $voucher): bool
    {
        return $user->managesTenant();
    }
}
