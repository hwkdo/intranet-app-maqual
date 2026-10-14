<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Enums;

enum QualQuelle: string
{
    case Manuell = 'manuell';
    case Magespraech = 'magespraech';

    public function label(): string
    {
        return match ($this) {
            self::Manuell => 'Manuell',
            self::Magespraech => 'Mitarbeitergespräch',
        };
    }
}
