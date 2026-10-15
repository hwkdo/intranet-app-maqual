<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Support;

use Hwkdo\IntranetAppMaqual\IntranetAppMaqual;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

class MaqualNavigation
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function items(): array
    {
        $user = Auth::user();

        $items = [
            [
                'label' => 'Meine Quals',
                'href' => route('apps.maqual.index'),
                'icon' => 'academic-cap',
                'description' => 'Eigene Qualifizierungen',
                'buttonText' => 'Meine Quals öffnen',
            ],
        ];

        if ($user instanceof Authenticatable && self::istVorgesetzter($user)) {
            $items[] = [
                'label' => 'Meine Mitarbeiter',
                'href' => route('apps.maqual.mitarbeiter'),
                'icon' => 'user-group',
                'description' => 'Qualifizierungen der eigenen Mitarbeiter',
                'buttonText' => 'Mitarbeiter anzeigen',
            ];
        }

        if ($user instanceof Authenticatable && app(GbZuordnung::class)->gefuehrteGbIds($user) !== []) {
            $items[] = [
                'label' => 'Budgets',
                'href' => route('apps.maqual.budgets'),
                'icon' => 'banknotes',
                'description' => 'Budget des Geschäftsbereichs',
                'buttonText' => 'Budgets anzeigen',
            ];
        }

        $items[] = [
            'label' => 'Personalabteilung',
            'href' => route('apps.maqual.personal'),
            'icon' => 'building-office',
            'description' => 'Alle Qualifizierungen und Budgets',
            'buttonText' => 'Personalabteilung öffnen',
            'permission' => 'manage-app-maqual',
        ];

        $items[] = [
            'label' => 'Benachrichtigungen',
            'href' => route('apps.maqual.settings.notifications'),
            'icon' => 'bell',
            'description' => 'Benachrichtigungen dieser App konfigurieren',
            'buttonText' => 'Benachrichtigungen öffnen',
        ];

        $items[] = [
            'label' => 'App-Info',
            'href' => route('apps.maqual.info'),
            'icon' => 'information-circle',
            'description' => 'Installierte Version und Release-Historie',
            'buttonText' => 'App-Info anzeigen',
        ];

        $items[] = [
            'label' => 'Hintergrund',
            'href' => route('apps.maqual.admin.background-activities'),
            'icon' => 'clock',
            'description' => 'Geplante Tasks, Jobs und Versand',
            'buttonText' => 'Hintergrund öffnen',
            'permission' => 'manage-app-maqual',
        ];

        $items[] = [
            'label' => 'Admin',
            'href' => route('apps.maqual.admin.index'),
            'icon' => 'shield-check',
            'description' => 'Administrationsbereich',
            'buttonText' => 'Admin öffnen',
            'permission' => 'manage-app-maqual',
        ];

        return $items;
    }

    public static function istVorgesetzter(Authenticatable $user): bool
    {
        return (bool) ($user->ist_vorgesetzter ?? false);
    }

    public static function appName(): string
    {
        return IntranetAppMaqual::app_name();
    }
}
