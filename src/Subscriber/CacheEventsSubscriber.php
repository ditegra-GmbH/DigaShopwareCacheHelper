<?php declare(strict_types=1);

namespace DigaShopwareCacheHelper\Subscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Shopware\Storefront\Framework\Cache\Event\HttpCacheHitEvent;
use Shopware\Storefront\Framework\Cache\Event\HttpCacheItemWrittenEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CacheEventsSubscriber implements EventSubscriberInterface
{
    /**
    * @var LoggerInterface
    */
    private $logger;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(LoggerInterface $logger, SystemConfigService $systemConfigService)
    {
        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
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
                         
            $logOnCacheHit = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logOnCacheHit');

            if($logOnCacheHit){
                $requestUri = $event->getRequest()->getRequestUri();
                $itemKey = $event->getItem()->getKey();        
                $this->logger->info('CacheHitEvent ItemKey: ' . $itemKey . ' RequestUri: '. $requestUri);
            }
            
        } catch (\Throwable $th) {       
            $this->logger->error( $th->getMessage());
        }        
    }

    public function onCacheItemWritten(HttpCacheItemWrittenEvent $event): void
    {
        try {
            $logOnCacheItemWritten = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logOnCacheItemWritten');

            if($logOnCacheItemWritten){
                $requestUri = $event->getRequest()->getRequestUri();
                $itemKey = $event->getItem()->getKey();        
                $tags = $event->getTags();
                $this->logger->info('CacheItemWrittenEvent ItemKey: ' . $itemKey . ' Tags: ' .  json_encode($tags) . ' RequestUri: '. $requestUri);
            }

        } catch (\Throwable $th) {
            $this->logger->error( $th->getMessage());
        }
    }

    public function onInvalidateCache(InvalidateCacheEvent $event): void
    {
        try {
            $logInvalidateCache = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logInvalidateCache');

            if($logInvalidateCache){
                $keys = $event->getKeys();
                $this->logger->info('InvalidateCacheEvent keys: ' .  json_encode($keys));
            }

        } catch (\Throwable $th) {
            $this->logger->error( $th->getMessage());
        }
    }
}