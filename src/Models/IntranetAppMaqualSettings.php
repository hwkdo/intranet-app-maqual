<?php

namespace Hwkdo\IntranetAppMaqual\Models;

use Hwkdo\IntranetAppMaqual\Data\AppSettings;
use Illuminate\Database\Eloquent\Model;

class IntranetAppMaqualSettings extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings' => AppSettings::class.':default',
        ];
    }

    public static function current(): ?IntranetAppMaqualSettings
    {
        return self::orderBy('version', 'desc')->first();
    }
}
