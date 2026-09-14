<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RADIUS accounting: a hot table plus a partitioned archive.
 *
 * radacct itself is deliberately NOT partitioned. MySQL requires every unique
 * key to contain the partitioning column, so partitioning by acctstarttime
 * would demote FreeRADIUS's UNIQUE(acctuniqueid) to a per-partition constraint.
 * That key is how interim updates and stop records find their open session, so
 * weakening it risks duplicate or orphaned sessions -- a correctness problem, in
 * exchange for a performance gain.
 *
 * The archive gets the partitioning instead. Closed sessions are moved there by
 * the retention job, which keeps the hot table bounded regardless of how much
 * history is retained, and lets whole months be discarded with an instant DROP
 * PARTITION rather than a large DELETE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radacct', function (Blueprint $table): void {
            $this->accountingColumns($table);

            $table->unique('acctuniqueid');
            $table->index('acctsessionid');

            // Per-voucher usage history, read by the accounting rollup.
            $table->index(['username', 'acctstarttime'], 'radacct_username_start_index');

            /*
             * Open sessions. "acctstoptime IS NULL" is a range condition on the
             * leading column, so this serves both the platform-wide enforcement
             * sweep and per-voucher simultaneous-use checks.
             */
            $table->index(['acctstoptime', 'username'], 'radacct_open_sessions_index');

            // Live session counts per router for the console dashboard.
            $table->index(['nasipaddress', 'acctstoptime'], 'radacct_nas_open_index');

            // Locating a session by IP when dispatching a CoA.
            $table->index('framedipaddress');
        });

        Schema::create('radacct_archive', function (Blueprint $table): void {
            $this->accountingColumns($table);
            $table->index(['username', 'acctstarttime'], 'radacct_archive_username_start_index');
        });

        /*
         * A partitioned table's unique keys must all include the partition
         * column, and partition columns cannot be nullable. Only completed
         * sessions are archived, so acctstarttime is always populated by then.
         */
        DB::statement('ALTER TABLE radacct_archive MODIFY acctstarttime DATETIME NOT NULL');
        DB::statement('ALTER TABLE radacct_archive DROP PRIMARY KEY, ADD PRIMARY KEY (radacctid, acctstarttime)');

        /*
         * Starts as a single catch-all. `kasi:radacct-partitions` reorganises
         * pmax into named monthly partitions ahead of time, so the boundaries
         * are not frozen to whenever this migration happened to run.
         */
        DB::statement('
            ALTER TABLE radacct_archive
            PARTITION BY RANGE (TO_DAYS(acctstarttime)) (
                PARTITION pmax VALUES LESS THAN MAXVALUE
            )
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('radacct_archive');
        Schema::dropIfExists('radacct');
    }

    /**
     * The stock FreeRADIUS 3.2 accounting columns, shared by both tables so
     * rows can be moved between them with a plain INSERT ... SELECT.
     */
    private function accountingColumns(Blueprint $table): void
    {
        $table->bigIncrements('radacctid');
        $table->string('acctsessionid', 64)->default('');
        $table->string('acctuniqueid', 32)->default('');
        $table->string('username', 64)->default('');
        $table->string('realm', 64)->nullable()->default('');

        // Widened from the stock varchar(15) to accommodate IPv6.
        $table->string('nasipaddress', 45)->default('');

        $table->string('nasportid', 32)->nullable();
        $table->string('nasporttype', 32)->nullable();
        $table->dateTime('acctstarttime')->nullable();
        $table->dateTime('acctupdatetime')->nullable();
        $table->dateTime('acctstoptime')->nullable();
        $table->integer('acctinterval')->nullable();
        $table->unsignedInteger('acctsessiontime')->nullable();
        $table->string('acctauthentic', 32)->nullable();
        $table->string('connectinfo_start', 128)->nullable();
        $table->string('connectinfo_stop', 128)->nullable();
        $table->unsignedBigInteger('acctinputoctets')->nullable();
        $table->unsignedBigInteger('acctoutputoctets')->nullable();

        // The hotspot server name, used to scope a session to its site.
        $table->string('calledstationid', 50)->default('');

        // The client device MAC, which is what voucher MAC binding compares.
        $table->string('callingstationid', 50)->default('');

        $table->string('acctterminatecause', 32)->default('');
        $table->string('servicetype', 32)->nullable();
        $table->string('framedprotocol', 32)->nullable();
        $table->string('framedipaddress', 45)->default('');
        $table->string('framedipv6address', 45)->default('');
        $table->string('framedipv6prefix', 45)->default('');
        $table->string('framedinterfaceid', 44)->default('');
        $table->string('delegatedipv6prefix', 45)->default('');
        $table->string('class', 64)->nullable();
    }
};
