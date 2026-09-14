<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RadAcctFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A RADIUS accounting session, written by FreeRADIUS rather than by this app.
 *
 * Treated as read-only here: the application observes sessions to roll up usage,
 * show live activity and decide who to disconnect, but the rows themselves belong
 * to the accounting stream.
 *
 * @property int $radacctid
 * @property string $acctsessionid
 * @property string $acctuniqueid
 * @property string $username
 * @property string $nasipaddress
 * @property Carbon|null $acctstarttime
 * @property Carbon|null $acctupdatetime
 * @property Carbon|null $acctstoptime
 * @property int|null $acctsessiontime
 * @property int|null $acctinputoctets
 * @property int|null $acctoutputoctets
 * @property string $callingstationid
 * @property string $calledstationid
 * @property string $framedipaddress
 */
class RadAcct extends Model
{
    /** @use HasFactory<RadAcctFactory> */
    use HasFactory;

    protected $table = 'radacct';

    protected $primaryKey = 'radacctid';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'acctstarttime' => 'datetime',
            'acctupdatetime' => 'datetime',
            'acctstoptime' => 'datetime',
            'acctsessiontime' => 'integer',
            'acctinputoctets' => 'integer',
            'acctoutputoctets' => 'integer',
        ];
    }

    /**
     * Sessions the router still considers active.
     *
     * @param  Builder<RadAcct>  $query
     * @return Builder<RadAcct>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('acctstoptime');
    }

    public function bytesTotal(): int
    {
        return (int) $this->acctinputoctets + (int) $this->acctoutputoctets;
    }

    /**
     * Seconds online.
     *
     * Falls back to wall-clock elapsed time because RouterOS only reports
     * Acct-Session-Time on interim updates and stops; a session that started
     * seconds ago has none yet, and treating that as zero would let a client
     * whose time is already spent reconnect indefinitely.
     */
    public function elapsedSeconds(): int
    {
        if ($this->acctsessiontime !== null) {
            return (int) $this->acctsessiontime;
        }

        if ($this->acctstarttime === null) {
            return 0;
        }

        return max(0, $this->acctstarttime->diffInSeconds(now()));
    }
}
