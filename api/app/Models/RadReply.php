<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A FreeRADIUS reply attribute, returned to the router on Access-Accept.
 *
 * @property int $id
 * @property string $username
 * @property string $attribute
 * @property string $op
 * @property string $value
 */
class RadReply extends Model
{
    protected $table = 'radreply';

    public $timestamps = false;

    protected $fillable = ['username', 'attribute', 'op', 'value'];
}
