<?php

declare(strict_types=1);

namespace DigaShopwareCacheHelper\Subscriber;

use DigaShopwareCacheHelper\Service\DigaLoggerFactoryService;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheHitEvent;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheStoreEvent;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Framework\Cache\CacheResponseSubscriber;

class CacheEventsSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly DigaLoggerFactoryService $logger, private readonly SystemConfigService $systemConfigService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            HttpCacheStoreEvent::class => 'onCacheItemWritten',
            HttpCacheHitEvent::class => 'onCacheHit',
            InvalidateCacheEvent::class => 'onInvalidateCache',
            HttpCacheKeyEvent::class => 'onHttpCacheGenerateKeyEvent'
        ];
    }

    public function onCacheHit(HttpCacheHitEvent $event): void
    {
        try {
            $logOnCacheHit = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logOnCacheHit');

            if ($logOnCacheHit) {
                $requestUri = $event->request->getRequestUri();
                $itemKey = $event->item->getKey();

                $ttl = $event->response->getTtl();
                $maxAge = $event->response->getMaxAge();

                $this->logger->info('CacheHitEvent | ' . $requestUri .' | ' . $itemKey . ' |  TTL: ' .  $ttl . ' maxAge: ' .  $maxAge);
            }
        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }

    public function onCacheItemWritten(HttpCacheStoreEvent $event): void
    {
        try {
            $logOnCacheItemWritten = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logOnCacheItemWritten');
            $logTagsOnCacheItemWritten = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logTagsOnCacheItemWritten');

            if ($logOnCacheItemWritten) {
                $requestUri = $event->request->getRequestUri();
                $itemKey = $event->item->getKey();
                $tags = $event->tags;

                $ttl = $event->response->getTtl();
                $maxAge = $event->response->getMaxAge();

                if ($logTagsOnCacheItemWritten) {
                    $this->logger->info('CacheItemWrittenEvent | ' . $requestUri .' | ' . $itemKey . ' |  TTL: ' .  $ttl . ' maxAge: ' .  $maxAge . ' Tags: ' .  json_encode($tags));
                } else {
                    $this->logger->info('CacheItemWrittenEvent | ' . $requestUri .' | ' . $itemKey . ' |  TTL: ' .  $ttl . ' maxAge: ' .  $maxAge);
                }
            }
        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }

    public function onInvalidateCache(InvalidateCacheEvent $event): void
    {
        try {
            $logInvalidateCache = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logInvalidateCache');

            if ($logInvalidateCache) {
                $keys = $event->getKeys();
                $this->logger->info('InvalidateCacheEvent |  |  |  keys ' . json_encode($keys));
            }
        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }

    public function onHttpCacheGenerateKeyEvent(HttpCacheKeyEvent $event): void
    {
        try {
            $logHttpCacheGenerateKeyEvent = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logHttpCacheGenerateKeyEvent');


            $cookies    = $event->request?->cookies;
            $attributes = $event->request?->attributes;

            if ($logHttpCacheGenerateKeyEvent && !empty($cookies) && !empty($attributes)) {
                $requestUri = $event->request->getRequestUri();
                //$hash = $event->getHash(); old from HttpCacheGenerateKeyEvent
                $hash = 'randomvalue'; //ToDo: should be fixed
                dump($event);
                $httpCacheKey = 'http-cache-' . $hash;
                
                if ($cookies->has(CacheResponseSubscriber::CONTEXT_CACHE_COOKIE)) {
                    $val = $cookies->get(CacheResponseSubscriber::CONTEXT_CACHE_COOKIE);
                    $httpCacheKey = 'http-cache-' . hash('sha256', $hash . '-' . $val);
                    $this->logger->info('HttpCacheGenerateKeyEvent | ' . $requestUri .' |  |  key ' .  $httpCacheKey . ' ' .CacheResponseSubscriber::CONTEXT_CACHE_COOKIE . ': ' . $val);
                    return;
                }

                if ($cookies->has(CacheResponseSubscriber::CURRENCY_COOKIE)) {
                    $val = $cookies->get(CacheResponseSubscriber::CURRENCY_COOKIE);
                    $httpCacheKey = 'http-cache-' . hash('sha256', $hash . '-' . $val);
                    $this->logger->info('HttpCacheGenerateKeyEvent | ' . $requestUri .' |  |  key ' .  $httpCacheKey . ' ' . CacheResponseSubscriber::CURRENCY_COOKIE . ': ' . $val);
                    return;
                }

                if ($attributes->has(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID)) {
                    $val = $attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID);
                    $httpCacheKey =  'http-cache-' . hash('sha256', $hash . '-' . $val);
                    $this->logger->info('HttpCacheGenerateKeyEvent | ' . $requestUri .' |  |  key ' .  $httpCacheKey . ' ' . SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID . ': ' . $val);
                    return;
                }

                $this->logger->info('HttpCacheGenerateKeyEvent | ' . $requestUri .' |  |  key ' .  $httpCacheKey . ' no cookies');
            }
        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }
}
