<?php

declare(strict_types=1);

namespace DigaShopwareCacheHelper\Decorator;

use DigaShopwareCacheHelper\Service\DigaLoggerFactoryService;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedHook;
use Shopware\Core\Content\Product\Events\InvalidateProductCache as ProductInvalidateEvent;
use Shopware\Core\Content\Category\Event\CategoryIndexerEvent;
use Shopware\Core\Content\LandingPage\Event\LandingPageIndexerEvent;
use Shopware\Core\Content\Media\Event\MediaIndexerEvent;
use Shopware\Core\Content\Sitemap\Event\SitemapGeneratedEvent;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Decorates Shopware's CacheInvalidator to add rich logging about invalidation triggers.
 */
class LoggingCacheInvalidator extends CacheInvalidator
{
    public function __construct(
        private readonly CacheInvalidator $inner,
        private readonly DigaLoggerFactoryService $logger,
        private readonly RequestStack $requestStack,
        private readonly string $kernelEnvironment,
    ) {
    // Do not call parent constructor; we fully override public methods and delegate to $inner
    }

    /**
     * @param array<string> $tags
     */
    public function invalidate(array $tags, bool $force = false): void
    {
        $request = $this->requestStack->getMainRequest();
        $invId = self::makeCorrelationId();
        $willForce = $force || $this->kernelEnvironment === 'test' || ($request?->headers->get(PlatformRequest::HEADER_FORCE_CACHE_INVALIDATE) === '1');
        $start = microtime(true);

        // capture deep backtrace with args for cause extraction
        $trace = debug_backtrace(0, 60);

        $this->inner->invalidate($tags, $force);

        $duration = (microtime(true) - $start) * 1000.0;

        try {
            $context = [
                'invId' => $invId,
                'tags' => $tags,
                'tagsCount' => \count($tags),
                'tagsClassified' => $this->classifyTags($tags),
                'forceParam' => $force,
                'willPurge' => $willForce,
                'durationMs' => (int) round($duration),
                'request' => $this->buildRequestInfo($request),
                'origin' => $this->detectOriginFromTrace($trace),
                'cause' => $this->extractCauseContext($trace),
            ];

            $this->logger->info('CacheInvalidator.invalidate', $context);
        } catch (\Throwable) {
            // do not block invalidation on logging errors
        }
    }

    /**
     * @return array<string>
     */
    public function invalidateExpired(): array
    {
        $result = $this->inner->invalidateExpired();

        try {
            $request = $this->requestStack->getMainRequest();
            $context = [
                'expiredTags' => $result,
                'expiredCount' => \count($result),
                'request' => $this->buildRequestInfo($request),
                'origin' => [
                    'viaScheduledTask' => true,
                    'source' => 'InvalidateCacheTaskHandler::run',
                ],
            ];
            $this->logger->info('CacheInvalidator.invalidateExpired', $context);
        } catch (\Throwable) {
        }

        return $result;
    }

    /**
     * Build a compact snapshot of the current request.
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
            'userAgent' => $headers->get('user-agent'),
            'ip' => $request->getClientIp(),
        ];
    }

    /**
     * Inspect current backtrace to find the caller which initiated the invalidation.
     * @return array<string, mixed>
     */
    private function detectOrigin(): array
    {
        $trace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 50);

        $origin = [
            'source' => null,
            'method' => null,
            'frames' => [],
        ];

        foreach ($trace as $frame) {
            $class = $frame['class'] ?? null;
            $function = $frame['function'] ?? null;
            $file = $frame['file'] ?? null;

            if ($class === 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber' && str_starts_with((string) $function, 'invalidate')) {
                $origin['source'] = $class;
                $origin['method'] = $function;
                break;
            }

            if ($class && $function && $class !== __CLASS__) {
                if (str_contains((string) $function, 'invalidate')) {
                    $origin['source'] = $class;
                    $origin['method'] = $function;
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

    /**
     * Same as detectOrigin() but works on a provided trace (with args) to avoid doing two backtraces.
     * @param array<int, array<string, mixed>> $trace
     * @return array<string, mixed>
     */
    private function detectOriginFromTrace(array $trace): array
    {
        $origin = [
            'source' => null,
            'method' => null,
            'frames' => [],
        ];

        foreach ($trace as $frame) {
            $class = $frame['class'] ?? null;
            $function = $frame['function'] ?? null;
            $file = $frame['file'] ?? null;

            if ($class === 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber' && str_starts_with((string) $function, 'invalidate')) {
                $origin['source'] = $class;
                $origin['method'] = $function;
                break;
            }

            if ($class && $function && $class !== __CLASS__) {
                if (str_contains((string) $function, 'invalidate')) {
                    $origin['source'] = $class;
                    $origin['method'] = $function;
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

    /**
     * Inspect trace args for known events to give a precise cause summary.
     * @param array<int, array<string, mixed>> $trace
     * @return array<string, mixed>
     */
    private function extractCauseContext(array $trace): array
    {
        $cause = [
            'entityWritten' => null,
            'systemConfigChanged' => null,
            'productChanged' => null,
            'mediaIndexed' => null,
            'categoryIndexed' => null,
            'landingPageIndexed' => null,
            'sitemapGenerated' => null,
        ];

        foreach ($trace as $frame) {
            $args = $frame['args'] ?? null;
            if (!$args || !\is_array($args)) {
                continue;
            }

            foreach ($args as $arg) {
                try {
                    if ($arg instanceof EntityWrittenContainerEvent && $cause['entityWritten'] === null) {
                        $entities = [];
                        $events = $arg->getEvents();
                        if ($events) {
                            foreach ($events as $e) {
                                if ($e instanceof \Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent) {
                                    $name = $e->getEntityName();
                                    $ids = $e->getIds();
                                    $entities[$name]['count'] = ($entities[$name]['count'] ?? 0) + \count($ids);
                                    if (!isset($entities[$name]['sample'])) {
                                        $entities[$name]['sample'] = array_slice($ids, 0, 3);
                                    }
                                }
                            }
                        }
                        $cause['entityWritten'] = [
                            'entities' => $entities,
                            'errorsCount' => \count($arg->getErrors()),
                            'cloned' => $arg->isCloned(),
                        ];
                    }

                    if ($arg instanceof SystemConfigChangedHook && $cause['systemConfigChanged'] === null) {
                        $cause['systemConfigChanged'] = [
                            'salesChannelId' => $arg->salesChannelId,
                            // changed keys are private in this class; we cannot access them safely
                        ];
                    }

                    if ($arg instanceof ProductInvalidateEvent && $cause['productChanged'] === null) {
                        $cause['productChanged'] = [
                            'idsCount' => \count($arg->getIds()),
                            'idsSample' => array_slice($arg->getIds(), 0, 5),
                            'force' => $arg->force,
                        ];
                    }

                    if ($arg instanceof MediaIndexerEvent && $cause['mediaIndexed'] === null) {
                        $cause['mediaIndexed'] = [
                            'idsCount' => \count($arg->getIds()),
                            'idsSample' => array_slice($arg->getIds(), 0, 5),
                        ];
                    }

                    if ($arg instanceof CategoryIndexerEvent && $cause['categoryIndexed'] === null) {
                        $cause['categoryIndexed'] = [
                            'idsCount' => \count($arg->getIds()),
                            'idsSample' => array_slice($arg->getIds(), 0, 5),
                        ];
                    }

                    if ($arg instanceof LandingPageIndexerEvent && $cause['landingPageIndexed'] === null) {
                        $cause['landingPageIndexed'] = [
                            'idsCount' => \count($arg->getIds()),
                            'idsSample' => array_slice($arg->getIds(), 0, 5),
                        ];
                    }

                    if ($arg instanceof SitemapGeneratedEvent && $cause['sitemapGenerated'] === null) {
                        $cause['sitemapGenerated'] = [
                            'salesChannelId' => $arg->getSalesChannelContext()->getSalesChannelId(),
                        ];
                    }
                } catch (\Throwable) {
                    // ignore extraction errors, continue scanning
                }
            }

            // stop early if we found at least one concrete cause
            if ($cause['entityWritten'] || $cause['systemConfigChanged'] || $cause['productChanged']) {
                break;
            }
        }

        return array_filter($cause, static fn ($v) => $v !== null);
    }

    /**
     * Classify tags into functional groups for easier reading.
     * @param array<int, string> $tags
     * @return array<string, array<string, mixed>>
     */
    private function classifyTags(array $tags): array
    {
        $groups = [
            'context' => [],
            'routing' => [],
            'product' => [],
            'listing' => [],
            'translator' => [],
            'stream' => [],
            'other' => [],
        ];

        foreach ($tags as $t) {
            if (str_starts_with($t, 'context-factory-') || str_starts_with($t, 'base-context-factory-')) {
                $groups['context'][] = $t;
            } elseif ($t === 'routing-domains') {
                $groups['routing'][] = $t;
            } elseif (str_starts_with($t, 'product-')) {
                $groups['product'][] = $t;
            } elseif (str_starts_with($t, 'listing-')) {
                $groups['listing'][] = $t;
            } elseif (str_starts_with($t, 'translator.')) {
                $groups['translator'][] = $t;
            } elseif (str_starts_with($t, 'stream-')) {
                $groups['stream'][] = $t;
            } else {
                $groups['other'][] = $t;
            }
        }

        $summary = [];
        foreach ($groups as $name => $list) {
            if (empty($list)) {
                continue;
            }
            $summary[$name] = [
                'count' => \count($list),
                'sample' => array_slice($list, 0, 5),
            ];
        }

        return $summary;
    }

    private static function makeCorrelationId(): string
    {
        $bin = random_bytes(8);
        return 'inv-' . bin2hex($bin);
    }
}
