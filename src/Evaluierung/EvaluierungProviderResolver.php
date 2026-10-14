<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Evaluierung;

use Hwkdo\IntranetAppMaqual\Exceptions\WorkflowException;
use Hwkdo\IntranetAppMaqual\Models\Qual;

class EvaluierungProviderResolver
{
    public function for(Qual $qual): EvaluierungProvider
    {
        if ($qual->evaluierung_provider === 'magespraech') {
            throw new WorkflowException('Die Evaluierung über Mitarbeitergespräche ist noch nicht angebunden.');
        }

        return app(InAppEvaluierungProvider::class);
    }
}
