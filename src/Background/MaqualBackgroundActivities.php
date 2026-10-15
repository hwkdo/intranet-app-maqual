<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Background;

use Hwkdo\IntranetAppBase\Data\BackgroundActivityDefinition;
use Hwkdo\IntranetAppBase\Data\BackgroundActivityFrequency;
use Hwkdo\IntranetAppBase\Enums\BackgroundActivityCadence;
use Hwkdo\IntranetAppMaqual\IntranetAppMaqual;

final class MaqualBackgroundActivities
{
    /**
     * @return list<BackgroundActivityDefinition>
     */
    public static function definitions(): array
    {
        return [
            BackgroundActivityDefinition::scheduled(
                key: 'maqual.fordere-teilnahme',
                label: 'Teilnahme anfordern',
                appIdentifier: IntranetAppMaqual::identifier(),
                appName: IntranetAppMaqual::app_name(),
                command: 'maqual:fordere-teilnahme',
                frequency: BackgroundActivityFrequency::dailyAt('00:00'),
                description: 'Fordert Teilnehmende zu Zertifikat oder Kosten auf.',
                allowedCadences: BackgroundActivityCadence::cases(),
            ),
        ];
    }
}
