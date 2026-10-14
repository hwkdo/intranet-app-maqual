<?php

namespace Hwkdo\IntranetAppMaqual\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Hwkdo\IntranetAppMaqual\IntranetAppMaqual
 */
class IntranetAppMaqual extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Hwkdo\IntranetAppMaqual\IntranetAppMaqual::class;
    }
}
