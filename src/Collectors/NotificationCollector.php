<?php

namespace LaravelPlus\DigDeep\Collectors;

use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;

class NotificationCollector
{
    /** @var array<int, array{notification: string, notifiable: string, channel: string, sent: bool}> */
    private array $notifications = [];

    public function listen(): void
    {
        Event::listen(NotificationSending::class, function (NotificationSending $event) {
            $notifiable = get_class($event->notifiable);

            if (method_exists($event->notifiable, 'getKey')) {
                $notifiable .= ':' . $event->notifiable->getKey();
            }

            $this->notifications[] = [
                'notification' => get_class($event->notification),
                'notifiable' => $notifiable,
                'channel' => $event->channel,
                'sent' => false,
            ];
        });

        Event::listen(NotificationSent::class, function (NotificationSent $event) {
            $notificationClass = get_class($event->notification);
            $channel = $event->channel;

            for ($i = count($this->notifications) - 1; $i >= 0; $i--) {
                if ($this->notifications[$i]['notification'] === $notificationClass
                    && $this->notifications[$i]['channel'] === $channel
                    && ! $this->notifications[$i]['sent']) {
                    $this->notifications[$i]['sent'] = true;

                    break;
                }
            }
        });
    }

    /** @return array<int, array{notification: string, notifiable: string, channel: string, sent: bool}> */
    public function getData(): array
    {
        return $this->notifications;
    }
}
