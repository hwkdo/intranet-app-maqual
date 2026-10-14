<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Notifications;

use Hwkdo\IntranetAppBase\Notifications\IntranetNotification;
use Hwkdo\IntranetAppMaqual\IntranetAppMaqual;
use Hwkdo\IntranetAppMaqual\Models\Qual;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

class NichtTeilgenommenNotification extends IntranetNotification
{
    public function __construct(
        public readonly Qual $qual,
    ) {
        parent::__construct();
    }

    public function typeKey(): string
    {
        return 'maqual.nicht_teilgenommen';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nicht teilgenommen: '.$this->qual->name)
            ->line($this->text())
            ->action('Qualifizierung öffnen', route('apps.maqual.quals.show', $this->qual));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->inboxPayload(
            title: 'Nicht teilgenommen',
            body: $this->text(),
            url: route('apps.maqual.quals.show', $this->qual),
            appIdentifier: IntranetAppMaqual::identifier(),
        );
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Nicht teilgenommen')
            ->body($this->text())
            ->data(['url' => route('apps.maqual.quals.show', $this->qual)]);
    }

    /**
     * @return array{preview: string, topic: string, url: string}
     */
    public function toTeams(object $notifiable): array
    {
        return [
            'preview' => $this->text(),
            'topic' => 'Mitarbeiterqualifizierung',
            'url' => route('apps.maqual.quals.show', $this->qual),
        ];
    }

    private function text(): string
    {
        $name = $this->qual->teilnehmer?->name ?? 'Ein Mitarbeiter';

        return $name.' hat an „'.$this->qual->name.'“ nicht teilgenommen.';
    }
}
