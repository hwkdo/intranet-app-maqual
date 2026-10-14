<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Notifications;

use Hwkdo\IntranetAppBase\Notifications\IntranetNotification;
use Hwkdo\IntranetAppMaqual\IntranetAppMaqual;
use Hwkdo\IntranetAppMaqual\Models\Qual;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

class EntscheidungNotification extends IntranetNotification
{
    public function __construct(
        public readonly Qual $qual,
        public readonly string $text,
    ) {
        parent::__construct();
    }

    public function typeKey(): string
    {
        return 'maqual.entscheidung';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Qualifizierung: '.$this->qual->name)
            ->line($this->text)
            ->action('Qualifizierung öffnen', route('apps.maqual.quals.show', $this->qual));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->inboxPayload(
            title: 'Qualifizierung aktualisiert',
            body: $this->text,
            url: route('apps.maqual.quals.show', $this->qual),
            appIdentifier: IntranetAppMaqual::identifier(),
        );
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Qualifizierung aktualisiert')
            ->body($this->text)
            ->data(['url' => route('apps.maqual.quals.show', $this->qual)]);
    }

    /**
     * @return array{preview: string, topic: string, url: string}
     */
    public function toTeams(object $notifiable): array
    {
        return [
            'preview' => $this->text,
            'topic' => 'Mitarbeiterqualifizierung',
            'url' => route('apps.maqual.quals.show', $this->qual),
        ];
    }
}
