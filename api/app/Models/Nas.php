<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * FreeRADIUS's client list.
 *
 * FreeRADIUS loads its RADIUS clients from this table when the sql module has
 * read_clients enabled, so adding a router in the console brings it online
 * without editing clients.conf or restarting the server.
 *
 * The secret is stored in cleartext because the RADIUS protocol requires the
 * server to hold it; NasDevice keeps the encrypted copy the console displays.
 * Kept in sync by App\Domain\Radius\NasSynchroniser.
 *
 * @property int $id
 * @property string $nasname
 * @property string|null $shortname
 * @property string $type
 * @property string $secret
 * @property string $description
 */
#[Hidden(['secret'])]
class Nas extends Model
{
    protected $table = 'nas';

    public $timestamps = false;

    protected $fillable = [
        'nasname',
        'shortname',
        'type',
        'ports',
        'secret',
        'server',
        'community',
        'description',
    ];
}
