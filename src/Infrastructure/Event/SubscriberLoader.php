<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Event;

use Bamise\Contract\EventDispatcherPortInterface;
use InvalidArgumentException;

final class SubscriberLoader
{
    public function load(EventDispatcherPortInterface $dispatcher, object $subscriber): void
    {
        if (! $subscriber instanceof EventSubscriberInterface) {
            throw new InvalidArgumentException(
                sprintf('Subscriber "%s" must implement EventSubscriberInterface.', get_class($subscriber)),
            );
        }

        foreach ($subscriber->getSubscribedEvents() as $eventClass => $config) {
            if (is_string($config)) {
                $method = $config;
                $priority = 0;
            } elseif (is_array($config) && isset($config[0])) {
                $method = (string) $config[0];
                $priority = (int) ($config[1] ?? 0);
            } else {
                throw new InvalidArgumentException(
                    sprintf('Invalid subscription config for event "%s".', (string) $eventClass),
                );
            }

            if (! method_exists($subscriber, $method)) {
                throw new InvalidArgumentException(
                    sprintf('Subscriber "%s" has no method "%s".', get_class($subscriber), $method),
                );
            }

            $dispatcher->subscribe(
                (string) $eventClass,
                $subscriber->{$method}(...),
                $priority,
            );
        }
    }
}
