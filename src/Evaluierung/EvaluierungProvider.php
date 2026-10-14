<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Evaluierung;

use Hwkdo\IntranetAppMaqual\Models\Qual;

interface EvaluierungProvider
{
    public function key(): string;

    public function isAvailable(): bool;

    public function starten(Qual $qual): void;
}
