<?php declare(strict_types=1);

namespace DigaShopwareCacheHelper\Subscriber;

use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Framework\Cache\Event\HttpCacheHitEvent;


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
            HttpCacheHitEvent::class => 'onCacheHit'
        ];
    }

    public function onCacheHit(CacheItemInterface $item, Request $request, Response $response): void
    {
        try {

            $requestUri = $request->getRequestUri();
            $itemKey = $item->getKey();        
            $this->logger->info('RequestUri: '. $requestUri .' ItemKey: ' . $itemKey);

        } catch (\Throwable $th) {       
            $this->logger->error( $th->getMessage());
        }        
    }
}