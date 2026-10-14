<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Notifications;

use Hwkdo\IntranetAppBase\Notifications\IntranetNotification;
use Hwkdo\IntranetAppMaqual\IntranetAppMaqual;
use Hwkdo\IntranetAppMaqual\Models\Qual;
use Hwkdo\IntranetAppMaqual\Support\QualFreigabeAdaptiveCard;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

class FreigabeErforderlichNotification extends IntranetNotification
{
    public function __construct(
        public readonly Qual $qual,
    ) {
        parent::__construct();
    }

    public function typeKey(): string
    {
        return 'maqual.freigabe_erforderlich';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Qualifizierung zur Freigabe: '.$this->qual->name)
            ->line('Die Qualifizierung „'.$this->qual->name.'“ wartet auf Ihre Entscheidung.')
            ->action('Qualifizierung öffnen', route('apps.maqual.quals.show', $this->qual));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->inboxPayload(
            title: 'Qualifizierung zur Freigabe',
            body: $this->qual->name.' wartet auf Ihre Entscheidung.',
            url: route('apps.maqual.quals.show', $this->qual),
            appIdentifier: IntranetAppMaqual::identifier(),
        );
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Qualifizierung zur Freigabe')
            ->body($this->qual->name)
            ->data(['url' => route('apps.maqual.quals.show', $this->qual)]);
    }

    /**
     * @return array{preview: string, topic: string, url: string, card: array<string, mixed>}
     */
    public function toTeams(object $notifiable): array
    {
        $this->qual->loadMissing(['teilnehmer', 'al', 'gf']);

        $azureUserId = $notifiable->socialite_id ?? null;
        $azureUserId = is_string($azureUserId) && $azureUserId !== ''
            ? strtolower($azureUserId)
            : null;

        return [
            'preview' => 'Qualifizierung „'.$this->qual->name.'“ wartet auf Freigabe.',
            'topic' => 'Mitarbeiterqualifizierung',
            'url' => route('apps.maqual.quals.show', $this->qual),
            'card' => QualFreigabeAdaptiveCard::forQual($this->qual, $azureUserId),
        ];
    }
}
