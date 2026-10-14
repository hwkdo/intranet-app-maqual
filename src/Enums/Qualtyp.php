<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Enums;

enum Qualtyp: string
{
    case Fachbezogen = 'fachbezogen';
    case Paedagogisch = 'paedagogisch';

    public function label(): string
    {
        return match ($this) {
            self::Fachbezogen => 'Fachbezogen',
            self::Paedagogisch => 'Pädagogisch',
        };
    }
}
