<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Keeps radacct_archive's monthly partitions ahead of the data.
 *
 * The archive is created with a single catch-all partition. This command splits
 * named monthly partitions off it in advance, so the boundaries follow real time
 * rather than whenever the migration happened to run, and drops months past the
 * retention window.
 *
 * Dropping a partition is effectively instant and reclaims the disk immediately,
 * which a DELETE over millions of accounting rows does neither of.
 */
class ManageRadacctPartitions extends Command
{
    protected $signature = 'kasi:radacct-partitions
                            {--months-ahead=3 : How many future months to pre-create}
                            {--retain-months= : Drop partitions older than this many months}
                            {--dry-run : Report the changes without applying them}';

    protected $description = 'Create upcoming and drop expired monthly partitions on radacct_archive';

    private const string TABLE = 'radacct_archive';

    private const string CATCH_ALL = 'pmax';

    public function handle(): int
    {
        $existing = $this->existingPartitions();

        if ($existing === []) {
            $this->components->error(self::TABLE.' is not partitioned.');

            return self::FAILURE;
        }

        $created = $this->createUpcoming($existing);
        $dropped = $this->dropExpired($existing);

        if ($created === 0 && $dropped === 0) {
            $this->components->info('Partitions already current.');
        }

        return self::SUCCESS;
    }

    /**
     * Splits future months off the catch-all partition.
     *
     * REORGANIZE is used rather than ADD because the catch-all holds MAXVALUE,
     * and MySQL will not add a partition above it.
     *
     * @param  array<int, string>  $existing
     */
    private function createUpcoming(array $existing): int
    {
        $monthsAhead = max(1, (int) $this->option('months-ahead'));
        $definitions = [];

        for ($offset = 0; $offset <= $monthsAhead; $offset++) {
            $month = Carbon::now()->startOfMonth()->addMonths($offset);
            $name = $this->partitionName($month);

            if (in_array($name, $existing, true)) {
                continue;
            }

            // Upper bound is exclusive: rows belong to a partition when their
            // start time is before the first day of the following month.
            $definitions[] = sprintf(
                "PARTITION %s VALUES LESS THAN (TO_DAYS('%s'))",
                $name,
                $month->copy()->addMonth()->toDateString(),
            );
        }

        if ($definitions === []) {
            return 0;
        }

        $definitions[] = sprintf('PARTITION %s VALUES LESS THAN MAXVALUE', self::CATCH_ALL);

        $sql = sprintf(
            'ALTER TABLE %s REORGANIZE PARTITION %s INTO (%s)',
            self::TABLE,
            self::CATCH_ALL,
            implode(', ', $definitions),
        );

        if ($this->option('dry-run')) {
            $this->line($sql.';');

            return count($definitions) - 1;
        }

        DB::statement($sql);

        $this->components->info(sprintf('Created %d partition(s).', count($definitions) - 1));

        return count($definitions) - 1;
    }

    /**
     * @param  array<int, string>  $existing
     */
    private function dropExpired(array $existing): int
    {
        $retain = $this->option('retain-months');

        if ($retain === null) {
            return 0;
        }

        $cutoff = Carbon::now()->startOfMonth()->subMonths(max(1, (int) $retain));
        $dropped = 0;

        foreach ($existing as $name) {
            if ($name === self::CATCH_ALL) {
                continue;
            }

            $month = $this->monthFromPartitionName($name);

            if (! $month instanceof Carbon || $month->greaterThanOrEqualTo($cutoff)) {
                continue;
            }

            $sql = sprintf('ALTER TABLE %s DROP PARTITION %s', self::TABLE, $name);

            if ($this->option('dry-run')) {
                $this->line($sql.';');
            } else {
                DB::statement($sql);
                $this->components->info("Dropped {$name}.");
            }

            $dropped++;
        }

        return $dropped;
    }

    /**
     * @return array<int, string>
     */
    private function existingPartitions(): array
    {
        $rows = DB::select(
            'SELECT PARTITION_NAME AS name
             FROM information_schema.PARTITIONS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND PARTITION_NAME IS NOT NULL',
            [self::TABLE],
        );

        return array_map(static fn (object $row): string => (string) $row->name, $rows);
    }

    private function partitionName(Carbon $month): string
    {
        return 'p'.$month->format('Y_m');
    }

    private function monthFromPartitionName(string $name): ?Carbon
    {
        if (preg_match('/^p(\d{4})_(\d{2})$/', $name, $matches) !== 1) {
            return null;
        }

        return Carbon::create((int) $matches[1], (int) $matches[2], 1)?->startOfMonth();
    }
}
