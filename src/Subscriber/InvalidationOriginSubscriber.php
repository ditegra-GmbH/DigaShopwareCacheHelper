<?php

declare(strict_types=1);

namespace DigaShopwareCacheHelper\Subscriber;

use DigaShopwareCacheHelper\Invalidation\InvalidationOriginRecorder;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * Records immediate invalidations, tells the recorder in which context (command, worker message)
 * invalidations happen and flushes its buffer once the request, command or message is done.
 * HTTP requests need no context here, the recorder reads the main request itself.
 */
class InvalidationOriginSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly InvalidationOriginRecorder $recorder)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InvalidateCacheEvent::class => 'onInvalidateCache',
            ConsoleEvents::COMMAND => 'onConsoleCommand',
            WorkerMessageReceivedEvent::class => 'onWorkerMessageReceived',
            // flush late, so invalidations from other terminate listeners are still included
            KernelEvents::TERMINATE => ['flush', -10000],
            ConsoleEvents::TERMINATE => ['flush', -10000],
            WorkerMessageHandledEvent::class => ['flush', -10000],
            WorkerMessageFailedEvent::class => ['flush', -10000],
        ];
    }

    /**
     * Dispatched synchronously inside CacheInvalidator::purge(), so the caller is still on the stack.
     * The resolver skips the scheduled purge of deferred tags.
     */
    public function onInvalidateCache(InvalidateCacheEvent $event): void
    {
        if ($this->recorder->isEnabled()) {
            $this->recorder->record($event->getKeys(), InvalidationOriginRecorder::MODE_IMMEDIATE);
        }
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $this->recorder->setContext('cli: ' . ($event->getCommand()?->getName() ?? 'unknown'));
    }

    public function onWorkerMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        // messenger:consume sets the cli context first, flush what belongs to it
        $this->recorder->reset();
        $this->recorder->setContext('worker: ' . $event->getEnvelope()->getMessage()::class);
    }

    public function flush(): void
    {
        $this->recorder->reset();
    }
}
