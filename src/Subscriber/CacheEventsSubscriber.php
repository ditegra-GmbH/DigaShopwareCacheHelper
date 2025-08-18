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
use Shopware\Core\Framework\Adapter\Cache\Http\CacheResponseSubscriber;
use Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Content\Product\Events\InvalidateProductCache;

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
            HttpCacheKeyEvent::class => 'onHttpCacheGenerateKeyEvent',
            InvalidateProductCache::class => 'onInvalidateProductCache'
        ];
    }

    public function onCacheHit(HttpCacheHitEvent $event): void
    {
        try {
            $logOnCacheHit = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logOnCacheHit');

            if ($logOnCacheHit) {
                $requestUri = $event->request->getRequestUri();
                $logFullUri = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logFullUri');
                if ($logFullUri) {
                    $requestUri = $event->request->getUri();
                }
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
                $logFullUri = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logFullUri');
                if ($logFullUri) {
                    $requestUri = $event->request->getUri();
                }
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
            $cookies    = $event->request->cookies;
            $attributes = $event->request->attributes;

            if ($logHttpCacheGenerateKeyEvent) {

                $requestUri = $event->request->getRequestUri();
                $logFullUri = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logFullUri');
                if ($logFullUri) {
                    $requestUri = $event->request->getUri();
                }
                
                if (Feature::isActive('v6.6.0.0')) {
                    
                    $parts = $event->getParts();
                    $httpCacheKey = 'http-cache-' . Hasher::hash(implode('|', $parts));

                    $this->logger->info('HttpCacheGenerateKeyEvent | ' . $requestUri .' |  |  key ' .  $httpCacheKey . ' | parts: ' . json_encode($parts));
                    return;
                }
                
                $hash = $event->get('hash');
                $httpCacheKey = 'http-cache-' . $hash;
                
                if ($cookies->has(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE)) {
                    $val = $cookies->get(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE);
                    $httpCacheKey = 'http-cache-' . hash('sha256', $hash . '-' . $val);
                    $this->logger->info('HttpCacheGenerateKeyEvent | ' . $requestUri .' |  |  key ' .  $httpCacheKey . ' ' .HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE . ': ' . $val);
                    return;
                }

                if ($cookies->has(HttpCacheKeyGenerator::CURRENCY_COOKIE)) {
                    $val = $cookies->get(HttpCacheKeyGenerator::CURRENCY_COOKIE);
                    $httpCacheKey = 'http-cache-' . hash('sha256', $hash . '-' . $val);
                    $this->logger->info('HttpCacheGenerateKeyEvent | ' . $requestUri .' |  |  key ' .  $httpCacheKey . ' ' . HttpCacheKeyGenerator::CURRENCY_COOKIE . ': ' . $val);
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

    public function onInvalidateProductCache(InvalidateProductCache $event): void
    {
        try {
            $logInvalidateProductCache = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logInvalidateProductCache');
            if ($logInvalidateProductCache) {
                $this->logger->info('InvalidateProductCache |  |  |  ids ' . json_encode($event->getIds()) . ' force: ' . ($event->force ? 'true' : 'false'));
            }
        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }
}
