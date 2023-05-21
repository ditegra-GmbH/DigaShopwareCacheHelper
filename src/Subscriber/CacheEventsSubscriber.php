<?php declare(strict_types=1);

namespace DigaShopwareCacheHelper\Subscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Shopware\Storefront\Framework\Cache\Event\HttpCacheHitEvent;
use Shopware\Storefront\Framework\Cache\Event\HttpCacheItemWrittenEvent;

class CacheEventsSubscriber implements EventSubscriberInterface
{
    /**
    * @var LoggerInterface
    */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array 
    {
        return [
            HttpCacheItemWrittenEvent::class => 'onCacheItemWritten',
            HttpCacheHitEvent::class => 'onCacheHit',
            InvalidateCacheEvent::class => 'onInvalidateCache'
        ];
    }

    public function onCacheHit(HttpCacheHitEvent $event): void
    {
        try {
            $requestUri = $event->getRequest()->getRequestUri();
            $itemKey = $event->getItem()->getKey();        
            $this->logger->info('CacheHitEvent ItemKey: ' . $itemKey . ' RequestUri: '. $requestUri);
        } catch (\Throwable $th) {       
            $this->logger->error( $th->getMessage());
        }        
    }

    public function onCacheItemWritten(HttpCacheItemWrittenEvent $event): void
    {
        try {
            
            $requestUri = $event->getRequest()->getRequestUri();
            $itemKey = $event->getItem()->getKey();        
            $tags = $event->getTags();
            $this->logger->info('CacheItemWrittenEvent ItemKey: ' . $itemKey . ' Tags: ' .  json_encode($tags) . ' RequestUri: '. $requestUri);

        } catch (\Throwable $th) {
            $this->logger->error( $th->getMessage());
        }
    }

    public function onInvalidateCache(InvalidateCacheEvent $event): void
    {
        try {

            $keys = $event->getKeys();
            $this->logger->info('InvalidateCacheEvent keys: ' .  json_encode($keys) );

        } catch (\Throwable $th) {
            $this->logger->error( $th->getMessage());
        }
    }
}