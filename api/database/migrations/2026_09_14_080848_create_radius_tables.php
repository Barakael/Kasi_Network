<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The FreeRADIUS authorisation tables.
 *
 * Column names and types follow the stock FreeRADIUS 3.2 MySQL schema because
 * the queries in mods-config/sql/main/mysql/queries.conf reference them
 * literally. They are declared here, in the same database as the application
 * tables, so the sqlcounter modules can join voucher_usage directly instead of
 * summing radacct on the authentication path.
 *
 * These tables carry no tenant_id: FreeRADIUS has no notion of tenancy. Tenant
 * isolation is enforced in the site policy, which resolves the operator from the
 * NAS the request arrived on and rejects vouchers belonging to anyone else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radcheck', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username', 64)->default('');
            $table->string('attribute', 64)->default('');
            $table->char('op', 2)->default('==');
            $table->string('value', 253)->default('');

            $table->index('username');
        });

        Schema::create('radreply', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username', 64)->default('');
            $table->string('attribute', 64)->default('');
            $table->char('op', 2)->default('=');
            $table->string('value', 253)->default('');

            $table->index('username');
        });

        Schema::create('radgroupcheck', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('groupname', 64)->default('');
            $table->string('attribute', 64)->default('');
            $table->char('op', 2)->default('==');
            $table->string('value', 253)->default('');

            $table->index('groupname');
        });

        Schema::create('radgroupreply', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('groupname', 64)->default('');
            $table->string('attribute', 64)->default('');
            $table->char('op', 2)->default('=');
            $table->string('value', 253)->default('');

            $table->index('groupname');
        });

        Schema::create('radusergroup', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username', 64)->default('');
            $table->string('groupname', 64)->default('');
            $table->integer('priority')->default(1);

            $table->index('username');
        });

        /*
         * Every authentication outcome, accept or reject. Kasi reads the reject
         * history to throttle voucher-code guessing per device, which is the
         * brute-force control on codes that double as RADIUS credentials.
         *
         * calledstationid and callingstationid are additions to the stock
         * schema; the postauth query in queries.conf is overridden to populate
         * them.
         */
        Schema::create('radpostauth', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('username', 64)->default('');
            $table->string('pass', 64)->default('');
            $table->string('reply', 32)->default('');
            $table->string('calledstationid', 50)->default('');
            $table->string('callingstationid', 50)->default('');
            $table->timestamp('authdate')->useCurrent();
            $table->string('class', 64)->nullable();

            $table->index('username');
            // Drives the per-device failed-attempt throttle.
            $table->index(['callingstationid', 'reply', 'authdate'], 'radpostauth_throttle_index');
        });

        /*
         * FreeRADIUS reads its client list from here when the sql module has
         * read_clients enabled, so adding a router in the console is enough to
         * bring it online without restarting the server.
         *
         * secret is cleartext because RADIUS itself requires it; nas_devices
         * holds the encrypted copy the console renders. Only the restricted
         * radius account can read this table.
         */
        Schema::create('nas', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nasname', 128);
            $table->string('shortname', 32)->nullable();
            $table->string('type', 30)->default('other');
            $table->integer('ports')->nullable();
            $table->string('secret', 60)->default('secret');
            $table->string('server', 64)->nullable();
            $table->string('community', 50)->nullable();
            $table->string('description', 200)->default('RADIUS Client');

            $table->unique('nasname');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nas');
        Schema::dropIfExists('radpostauth');
        Schema::dropIfExists('radusergroup');
        Schema::dropIfExists('radgroupreply');
        Schema::dropIfExists('radgroupcheck');
        Schema::dropIfExists('radreply');
        Schema::dropIfExists('radcheck');
    }
};
