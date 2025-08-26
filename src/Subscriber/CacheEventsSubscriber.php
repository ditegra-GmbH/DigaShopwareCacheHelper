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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Shopware\Core\PlatformRequest;

class CacheEventsSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly DigaLoggerFactoryService $logger, private readonly SystemConfigService $systemConfigService, private readonly RequestStack $requestStack)
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

                $request = $this->requestStack->getMainRequest();
                $requestInfo = $this->buildRequestInfo($request);

                $origin = $this->detectInvalidationOrigin();

                $context = [
                    'keys' => $keys,
                    'keysCount' => \count($keys),
                    'request' => $requestInfo,
                    'origin' => $origin,
                ];

                $this->logger->info('InvalidateCacheEvent', $context);
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
                $request = $this->requestStack->getMainRequest();
                $this->logger->info('InvalidateProductCache', [
                    'ids' => $event->getIds(),
                    'force' => $event->force,
                    'request' => $this->buildRequestInfo($request),
                    'origin' => $this->detectInvalidationOrigin(),
                ]);
            }
        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }

    /**
     * Build a compact snapshot of the current request including route, scope and selected headers.
     * @return array<string, mixed>
     */
    private function buildRequestInfo(?Request $request): array
    {
        if (!$request) {
            return ['cli' => \PHP_SAPI === 'cli', 'note' => 'no active HTTP request'];
        }

        $headers = $request->headers;

        $sanitize = static function (?string $val): ?string {
            if ($val === null || $val === '') {
                return $val;
            }
            // sanitize potentially sensitive tokens by hashing
            return substr(hash('sha256', $val), 0, 12);
        };

        $routeScope = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE);

        return [
            'method' => $request->getMethod(),
            'uri' => $request->getUri(),
            'route' => $request->attributes->get('_route'),
            'controller' => $request->attributes->get('_controller'),
            'routeScope' => \is_array($routeScope) ? $routeScope : ($routeScope ? [$routeScope] : []),
            'salesChannelId' => $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID),
            'forceHeader' => $headers->get(PlatformRequest::HEADER_FORCE_CACHE_INVALIDATE) === '1',
            'contextToken' => $sanitize($headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN)),
            'accessKey' => $sanitize($headers->get(PlatformRequest::HEADER_ACCESS_KEY)),
            'languageId' => $headers->get(PlatformRequest::HEADER_LANGUAGE_ID),
            'currencyId' => $headers->get(PlatformRequest::HEADER_CURRENCY_ID),
            'xForwardedFor' => $headers->get('x-forwarded-for'),
            'xRequestId' => $headers->get('x-request-id'),
            'userAgent' => $headers->get('user-agent'),
            'ip' => $request->getClientIp(),
        ];
    }

    /**
     * Inspect the current backtrace to determine the most likely invalidation trigger.
     * @return array<string, mixed>
     */
    private function detectInvalidationOrigin(): array
    {
        $trace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 50);

        $origin = [
            'source' => null,
            'method' => null,
            'viaScheduledTask' => false,
            'viaInvalidateExpired' => false,
            'frames' => [],
        ];

        foreach ($trace as $frame) {
            $class = $frame['class'] ?? null;
            $function = $frame['function'] ?? null;
            $file = $frame['file'] ?? null;

            // detect scheduled invalidation (expired tags)
            if ($class === 'Shopware\\Core\\Framework\\Adapter\\Cache\\InvalidateCacheTaskHandler' && $function === 'run') {
                $origin['viaScheduledTask'] = true;
            }

            if ($class === 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidator' && $function === 'invalidateExpired') {
                $origin['viaInvalidateExpired'] = true;
            }

            // prefer Shopware's CacheInvalidationSubscriber invalidate* methods as primary cause
            if ($class === 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber' && str_starts_with((string) $function, 'invalidate')) {
                $origin['source'] = $class;
                $origin['method'] = $function;
                break;
            }

            // otherwise, first non-internal class calling invalidate on CacheInvalidator
            if ($class && $function && $class !== __CLASS__ && $class !== 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidator') {
                if (str_contains((string) $function, 'invalidate')) {
                    $origin['source'] = $class;
                    $origin['method'] = $function;
                    // don't break yet, we still prefer the dedicated subscriber if it appears later
                }
            }

            if ($class || $function || $file) {
                $origin['frames'][] = [
                    'class' => $class,
                    'function' => $function,
                    'file' => $file,
                ];
            }
        }

        return $origin;
    }
}
