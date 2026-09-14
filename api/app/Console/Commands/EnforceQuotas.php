<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Radius\QuotaEnforcer;
use Illuminate\Console\Command;

class EnforceQuotas extends Command
{
    protected $signature = 'kasi:enforce-quotas';

    protected $description = 'Disconnect or throttle open sessions that have overrun their bundle';

    public function handle(QuotaEnforcer $enforcer): int
    {
        $acted = $enforcer->enforce();

        $this->components->info("Enforced {$acted} session(s).");

        return self::SUCCESS;
    }
}
