<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual;

use Hwkdo\IntranetAppBase\Interfaces\ProvidesBackgroundActivitiesInterface;
use Hwkdo\IntranetAppMaqual\Background\MaqualBackgroundActivities;
use Hwkdo\IntranetAppBase\Data\NotificationTypeDefinition;
use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesNotificationsInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesTasksInterface;
use Hwkdo\IntranetAppMaqual\Tasks\OffeneQualAktionenTaskProvider;
use Illuminate\Support\Collection;

class IntranetAppMaqual implements IntranetAppInterface, ProvidesBackgroundActivitiesInterface, ProvidesNotificationsInterface, ProvidesTasksInterface
{
    public static function app_name(): string
    {
        return 'Mitarbeiterqualifizierung';
    }

    public static function app_icon(): string
    {
        return 'academic-cap';
    }

    public static function identifier(): string
    {
        return 'maqual';
    }

    public static function roles_admin(): Collection
    {
        return collect(config('intranet-app-maqual.roles.admin'));
    }

    public static function roles_user(): Collection
    {
        return collect(config('intranet-app-maqual.roles.user'));
    }

    public static function userSettingsClass(): ?string
    {
        return null;
    }

    public static function appSettingsClass(): ?string
    {
        return Data\AppSettings::class;
    }

    public static function mcpServers(): array
    {
        return [];
    }

    /**
     * @return list<NotificationTypeDefinition>
     */
    public static function notificationTypes(): array
    {
        return [
            new NotificationTypeDefinition(
                key: 'maqual.freigabe_erforderlich',
                label: 'Freigabe erforderlich',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                description: 'Eine Qualifizierung wartet auf Ihre Zustimmung oder die Budgetprüfung.',
                mandatory: true,
                defaultChannels: ['inbox', 'mail'],
            ),
            new NotificationTypeDefinition(
                key: 'maqual.entscheidung',
                label: 'Entscheidung zur Qualifizierung',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                description: 'Eine Qualifizierung wurde genehmigt, abgelehnt oder abgeschlossen.',
                mandatory: false,
                defaultEnabled: true,
                defaultChannels: ['inbox', 'mail'],
            ),
            new NotificationTypeDefinition(
                key: 'maqual.teilnehmer_aktion',
                label: 'Aktion als Teilnehmende',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                description: 'Anmeldung, Teilnahmebestätigung, Bewertung oder Evaluierung ist fällig.',
                mandatory: true,
                defaultChannels: ['inbox', 'mail'],
            ),
            new NotificationTypeDefinition(
                key: 'maqual.nicht_teilgenommen',
                label: 'Nicht teilgenommen',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                description: 'Eine Person hat an einer Qualifizierung nicht teilgenommen.',
                mandatory: true,
                defaultChannels: ['inbox', 'mail'],
            ),
        ];
    }

    /**
     * @return array<class-string>
     */
    public static function taskProviders(): array
    {
        return [
            OffeneQualAktionenTaskProvider::class,
        ];
    }

    public static function backgroundActivities(): array
    {
        return MaqualBackgroundActivities::definitions();
    }


}
