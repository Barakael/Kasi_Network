<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Grants FreeRADIUS the narrowest MySQL access it can work with.
 *
 * Run after migrating. It cannot be folded into the migration or the container's
 * init script because MySQL refuses table-level grants for tables that do not
 * exist yet, and both of those run before the tables are created.
 */
class ProvisionRadiusUser extends Command
{
    protected $signature = 'kasi:provision-radius-user
                            {--user= : MySQL account name, defaults to config}
                            {--password= : Account password, defaults to config}
                            {--host= : Host pattern the account may connect from}
                            {--print : Show the statements without executing them}';

    protected $description = 'Create or update the restricted MySQL account FreeRADIUS connects as';

    /**
     * Exactly what FreeRADIUS needs, and nothing else.
     *
     * Notably absent: any access to `tenants`, which holds operators' encrypted
     * Snippe credentials, and to `users` and `orders`. A compromised RADIUS host
     * should not be able to read an operator's payment keys or customer records.
     *
     * radcheck is writable because activation happens during authentication: the
     * post-auth hook stamps the Expiration that starts a bundle's clock and the
     * Calling-Station-Id that binds the code to its first device. Doing that from
     * the application instead would leave a window in which a code could be used
     * on two devices at once.
     *
     * @var array<string, array<int, string>>
     */
    private const array GRANTS = [
        'radcheck' => ['SELECT', 'INSERT', 'UPDATE'],
        'radreply' => ['SELECT'],
        'radgroupcheck' => ['SELECT'],
        'radgroupreply' => ['SELECT'],
        'radusergroup' => ['SELECT'],
        'radacct' => ['SELECT', 'INSERT', 'UPDATE'],
        'radpostauth' => ['SELECT', 'INSERT'],
        'nas' => ['SELECT'],

        // Quota counters and the MAC-to-voucher resolution the site policy does.
        'voucher_usage' => ['SELECT'],
        'voucher_devices' => ['SELECT'],
        'vouchers' => ['SELECT'],

        // Resolving which operator a request's router belongs to.
        'sites' => ['SELECT'],
        'nas_devices' => ['SELECT'],
    ];

    public function handle(): int
    {
        $user = (string) ($this->option('user') ?: config('kasi.radius.db_user'));
        $password = (string) ($this->option('password') ?: config('kasi.radius.db_password'));
        $host = (string) ($this->option('host') ?: config('kasi.radius.db_host_pattern'));
        $database = (string) config('database.connections.'.config('database.default').'.database');

        if ($password === '') {
            $this->components->error('No password configured. Set RADIUS_DB_PASSWORD or pass --password.');

            return self::FAILURE;
        }

        $statements = $this->statements($user, $password, $host, $database);

        if ($this->option('print')) {
            foreach ($statements as $statement) {
                $this->line($statement.';');
            }

            return self::SUCCESS;
        }

        foreach ($statements as $statement) {
            try {
                DB::statement($statement);
            } catch (Throwable $e) {
                $this->components->error("Failed: {$statement}");
                $this->line($e->getMessage());

                return self::FAILURE;
            }
        }

        $this->components->info(sprintf(
            "Granted '%s'@'%s' access to %d tables in %s.",
            $user,
            $host,
            count(self::GRANTS),
            $database,
        ));

        $this->components->warn(
            'radcheck and nas hold cleartext voucher codes and RADIUS shared secrets. '
            .'Both are required by the protocol; keep this account off any other host.'
        );

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function statements(string $user, string $password, string $host, string $database): array
    {
        $account = sprintf("'%s'@'%s'", $user, $host);

        $statements = [
            sprintf(
                'CREATE USER IF NOT EXISTS %s IDENTIFIED BY %s',
                $account,
                $this->quote($password),
            ),
            sprintf(
                'ALTER USER %s IDENTIFIED BY %s',
                $account,
                $this->quote($password),
            ),
            /*
             * Revoke everything first so removing a table from GRANTS actually
             * withdraws access instead of leaving a privilege behind from an
             * earlier run.
             *
             * This form rather than `REVOKE ALL ON db.*`: a database-level revoke
             * does not clear table-level grants, and it errors outright when there
             * is nothing to revoke. Revoking from the account is idempotent and
             * catches table grants too.
             */
            sprintf('REVOKE ALL PRIVILEGES, GRANT OPTION FROM %s', $account),
        ];

        foreach (self::GRANTS as $table => $privileges) {
            $statements[] = sprintf(
                'GRANT %s ON `%s`.`%s` TO %s',
                implode(', ', $privileges),
                $database,
                $table,
                $account,
            );
        }

        return $statements;
    }

    /**
     * MySQL will not accept a bound parameter in a GRANT or CREATE USER, so the
     * password has to be inlined. Escaped rather than interpolated raw.
     */
    private function quote(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }
}
