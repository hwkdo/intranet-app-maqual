<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Evaluierung;

use Hwkdo\IntranetAppMaqual\Models\Evaluierung;
use Hwkdo\IntranetAppMaqual\Models\Qual;

class InAppEvaluierungProvider implements EvaluierungProvider
{
    public function key(): string
    {
        return 'in_app';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function starten(Qual $qual): void
    {
        Evaluierung::query()->firstOrCreate(
            ['qual_id' => $qual->getKey()],
            ['data' => null],
        );
    }
}
