<?php declare(strict_types=1);

namespace DigaShopwareCacheHelper\Subscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Framework\Cache\Event\HttpCacheHitEvent;
use Shopware\Storefront\Framework\Cache\Event\HttpCacheGenerateKeyEvent;
use Shopware\Storefront\Framework\Cache\Event\HttpCacheItemWrittenEvent;

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
            InvalidateCacheEvent::class => 'onInvalidateCache',
            HttpCacheGenerateKeyEvent::class => 'onHttpCacheGenerateKeyEvent'
        ];
    }

    public function onCacheHit(HttpCacheHitEvent $event): void
    {
        try {
                         
            $logOnCacheHit = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logOnCacheHit');

            if($logOnCacheHit){
                $requestUri = $event->getRequest()->getRequestUri();
                $itemKey = $event->getItem()->getKey();        
          
                $ttl = $event->getResponse()->getTtl();
                $maxAge = $event->getResponse()->getMaxAge();

                $this->logger->info('CacheHitEvent ItemKey: ' . $itemKey . ' TTL: ' .  $ttl . ' maxAge: ' .  $maxAge . ' RequestUri: '. $requestUri);
            }
            
        } catch (\Throwable $th) {       
            $this->logger->error( $th->getMessage());
        }        
    }

    public function onCacheItemWritten(HttpCacheItemWrittenEvent $event): void
    {
        try {
            $logOnCacheItemWritten = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logOnCacheItemWritten');
            $logTagsOnCacheItemWritten = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logTagsOnCacheItemWritten');

            if($logOnCacheItemWritten){
                $requestUri = $event->getRequest()->getRequestUri();
                $itemKey = $event->getItem()->getKey();        
                $tags = $event->getTags();
       
                $ttl = $event->getResponse()->getTtl();
                $maxAge = $event->getResponse()->getMaxAge();
                
                if($logTagsOnCacheItemWritten){
                    $this->logger->info('CacheItemWrittenEvent ItemKey: ' . $itemKey . ' Tags: ' .  json_encode($tags) . ' TTL: ' .  $ttl . ' maxAge: ' .  $maxAge . ' RequestUri: '. $requestUri);
                }else{
                    $this->logger->info('CacheItemWrittenEvent ItemKey: ' . $itemKey . ' TTL: ' .  $ttl . ' maxAge: ' .  $maxAge . ' RequestUri: '. $requestUri);
                }                
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

    public function onHttpCacheGenerateKeyEvent(HttpCacheGenerateKeyEvent $event): void
    {
        try {
                         
            $logHttpCacheGenerateKeyEvent = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logHttpCacheGenerateKeyEvent');

            if($logHttpCacheGenerateKeyEvent){
                $requestUri = $event->getRequest()->getRequestUri();
                $hash = $event->getHash();        
                $this->logger->info('HttpCacheGenerateKeyEvent hash: ' . $hash . ' RequestUri: '. $requestUri);
            }
            
        } catch (\Throwable $th) {       
            $this->logger->error( $th->getMessage());
        }
    }
}