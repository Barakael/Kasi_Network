<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A FreeRADIUS authorisation check attribute.
 *
 * Rows here are what actually let a client online, so this is the boundary
 * between application state and the RADIUS server. Writes go through
 * App\Domain\Radius\RadiusProvisioner rather than being made ad hoc, because a
 * voucher needs a consistent set of attributes to behave correctly.
 *
 * @property int $id
 * @property string $username
 * @property string $attribute
 * @property string $op
 * @property string $value
 */
class RadCheck extends Model
{
    protected $table = 'radcheck';

    public $timestamps = false;

    protected $fillable = ['username', 'attribute', 'op', 'value'];
}
