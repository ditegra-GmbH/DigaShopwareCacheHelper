<?php declare(strict_types=1);

namespace DigaShopwareCacheHelper\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\HttpFoundation\Response;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Framework\Cache\CacheResponseSubscriber;
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

                $this->logger->info('CacheHitEvent | ' . $requestUri .' | ' . $itemKey . ' |  TTL: ' .  $ttl . ' maxAge: ' .  $maxAge);
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

                    $this->logger->info('CacheItemWrittenEvent | ' . $requestUri .' | ' . $itemKey . ' |  TTL: ' .  $ttl . ' maxAge: ' .  $maxAge . ' Tags: ' .  json_encode($tags));

                }else{

                    $this->logger->info('CacheItemWrittenEvent | ' . $requestUri .' | ' . $itemKey . ' |  TTL: ' .  $ttl . ' maxAge: ' .  $maxAge);
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
                $this->logger->info('InvalidateCacheEvent |  |  |  keys ' . json_encode($keys));
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

                $httpCacheKey = 'http-cache-' . $hash;

                if ($event->getRequest()->cookies->has(CacheResponseSubscriber::CONTEXT_CACHE_COOKIE)) {
                    $val = $event->getRequest()->cookies->get(CacheResponseSubscriber::CONTEXT_CACHE_COOKIE);
                    $httpCacheKey = 'http-cache-' . hash('sha256', $hash . '-' . $val);
                    $this->logger->info('HttpCacheGenerateKeyEvent | ' . $requestUri .' |  |  key ' .  $httpCacheKey . ' ' .CacheResponseSubscriber::CONTEXT_CACHE_COOKIE . ': ' . $val );
                }
        
                if ($event->getRequest()->cookies->has(CacheResponseSubscriber::CURRENCY_COOKIE)) {
                    $val = $event->getRequest()->cookies->get(CacheResponseSubscriber::CURRENCY_COOKIE);
                    $httpCacheKey = 'http-cache-' . hash('sha256', $hash . '-' . $val);
                    $this->logger->info('HttpCacheGenerateKeyEvent | ' . $requestUri .' |  |  key ' .  $httpCacheKey . ' ' . CacheResponseSubscriber::CURRENCY_COOKIE . ': ' . $val );
                }
        
                if ($event->getRequest()->attributes->has(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID)) {
                    $val = $event->getRequest()->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID);
                    $httpCacheKey =  'http-cache-' . hash('sha256', $hash . '-' . $val);                    
                    $this->logger->info('HttpCacheGenerateKeyEvent | ' . $requestUri .' |  |  key ' .  $httpCacheKey . ' ' . SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID . ': ' . $val);
                }
                
                $this->logger->info('HttpCacheGenerateKeyEvent | ' . $requestUri .' |  |  key ' .  $httpCacheKey . ' no cookies' );
            }
            
        } catch (\Throwable $th) {       
            $this->logger->error( $th->getMessage());
        }
    }
}