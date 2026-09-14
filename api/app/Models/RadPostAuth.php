<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Every authentication attempt and its outcome.
 *
 * Read by the portal's brute-force control: because voucher codes double as
 * RADIUS credentials, a client hammering the router's own login page never
 * touches the API, so the reject history recorded here is the only place those
 * attempts are visible.
 *
 * @property int $id
 * @property string $username
 * @property string $reply
 * @property string $callingstationid
 * @property Carbon $authdate
 */
class RadPostAuth extends Model
{
    protected $table = 'radpostauth';

    public $timestamps = false;

    /** Reply value FreeRADIUS records for a successful authentication. */
    public const string ACCEPT = 'Access-Accept';

    /** Reply value FreeRADIUS records for a refusal. */
    public const string REJECT = 'Access-Reject';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'authdate' => 'datetime',
        ];
    }

    /**
     * @param  Builder<RadPostAuth>  $query
     * @return Builder<RadPostAuth>
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('reply', self::REJECT);
    }
}
