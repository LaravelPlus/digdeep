<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Collectors;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;

final class MailCollector
{
    /** @var array<int, array{to: string, subject: string}> */
    private array $mails = [];

    public function listen(): void
    {
        Event::listen(MessageSending::class, function (MessageSending $event): void {
            $message = $event->message;

            $to = array_map(fn ($addr) => $addr->getAddress(), $message->getTo());

            $this->mails[] = [
                'to' => implode(', ', $to),
                'subject' => $message->getSubject() ?? '(no subject)',
            ];
        });
    }

    /** @return array<int, array{to: string, subject: string}> */
    public function getData(): array
    {
        return $this->mails;
    }
}
