<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * How an operator pays Kasi for the platform. One row, edited by the Super Admin.
 *
 * @property int $id
 * @property string|null $payee_name
 * @property string|null $account_number
 * @property string|null $instructions
 */
class PlatformProfile extends Model
{
    protected $fillable = [
        'payee_name',
        'account_number',
        'instructions',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
