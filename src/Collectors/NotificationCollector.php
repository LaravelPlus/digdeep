<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Collectors;

use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;

final class NotificationCollector
{
    /** @var array<int, array{notification: string, notifiable: string, channel: string, sent: bool}> */
    private array $notifications = [];

    public function listen(): void
    {
        Event::listen(NotificationSending::class, function (NotificationSending $event): void {
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

        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            $notificationClass = get_class($event->notification);
            $channel = $event->channel;

            $index = array_find_key(
                array_reverse($this->notifications, preserve_keys: true),
                fn ($n) => $n['notification'] === $notificationClass
                    && $n['channel'] === $channel
                    && !$n['sent'],
            );

            if ($index !== null) {
                $this->notifications[$index]['sent'] = true;
            }
        });
    }

    /** @return array<int, array{notification: string, notifiable: string, channel: string, sent: bool}> */
    public function getData(): array
    {
        return $this->notifications;
    }
}
