<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Radius\AccountingRollup;
use Illuminate\Console\Command;

class RollupAccounting extends Command
{
    protected $signature = 'kasi:rollup-usage';

    protected $description = 'Fold closed RADIUS sessions into voucher_usage rollups';

    public function handle(AccountingRollup $rollup): int
    {
        $updated = $rollup->rollup();

        $this->components->info("Rolled up {$updated} voucher(s).");

        return self::SUCCESS;
    }
}
