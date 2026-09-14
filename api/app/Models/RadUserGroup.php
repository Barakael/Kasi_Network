<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Maps a RADIUS username onto a group whose attributes it inherits.
 *
 * @property int $id
 * @property string $username
 * @property string $groupname
 * @property int $priority
 */
class RadUserGroup extends Model
{
    protected $table = 'radusergroup';

    public $timestamps = false;

    protected $fillable = ['username', 'groupname', 'priority'];
}
