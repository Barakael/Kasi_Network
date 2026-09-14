<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Moves closed radacct rows into the partitioned archive so the hot table stays
 * bounded. Unique(acctuniqueid) stays on the hot table, which is why archival
 * is a move rather than partitioning radacct itself.
 */
class ArchiveRadacct extends Command
{
    protected $signature = 'kasi:radacct-archive
                            {--older-than-days=14 : Move sessions that stopped at least this many days ago}';

    protected $description = 'Move closed RADIUS sessions from radacct into radacct_archive';

    public function handle(): int
    {
        $cutoff = now()->subDays(max(1, (int) $this->option('older-than-days')));
        $moved = 0;

        do {
            $ids = DB::table('radacct')
                ->whereNotNull('acctstoptime')
                ->where('acctstoptime', '<', $cutoff)
                ->orderBy('radacctid')
                ->limit(1000)
                ->pluck('radacctid');

            if ($ids->isEmpty()) {
                break;
            }

            DB::transaction(function () use ($ids, &$moved): void {
                $columns = 'radacctid, acctsessionid, acctuniqueid, username, realm, nasipaddress, nasportid, nasporttype, acctstarttime, acctupdatetime, acctstoptime, acctsessiontime, acctauthentic, connectinfo_start, connectinfo_stop, acctinputoctets, acctoutputoctets, calledstationid, callingstationid, acctterminatecause, servicetype, framedprotocol, framedipaddress';

                DB::statement(
                    "INSERT INTO radacct_archive ({$columns}) SELECT {$columns} FROM radacct WHERE radacctid IN (".$ids->implode(',').')',
                );

                DB::table('radacct')->whereIn('radacctid', $ids)->delete();
                $moved += $ids->count();
            });
        } while (true);

        $this->components->info("Archived {$moved} session(s).");

        return self::SUCCESS;
    }
}
