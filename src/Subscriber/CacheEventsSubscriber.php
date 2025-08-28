<?php

declare(strict_types=1);

namespace DigaShopwareCacheHelper\Subscriber;

use Shopware\Core\PlatformRequest;
use Shopware\Core\Framework\Feature;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use DigaShopwareCacheHelper\Service\DigaLoggerFactoryService;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheHitEvent;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheStoreEvent;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheResponseSubscriber;

class CacheEventsSubscriber implements EventSubscriberInterface
{
	public function __construct(
		private readonly DigaLoggerFactoryService $logger,
		private readonly SystemConfigService $systemConfigService,
		private readonly RequestStack $requestStack
	) {}

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
					$this->logger->info('CacheItemWrittenEvent', [
						'uri' => $requestUri,
						'key' => $itemKey,
						'ttl' => $ttl,
						'maxAge' => $maxAge,
						'tagsCount' => \count($tags),
						'tagsSample' => \array_slice($tags, 0, 25),
					]);
				} else {
					$this->logger->info('CacheItemWrittenEvent', [
						'uri' => $requestUri,
						'key' => $itemKey,
						'ttl' => $ttl,
						'maxAge' => $maxAge,
					]);
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
				$req = $this->requestStack->getMainRequest();
				$trace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 60);
				$context = [
					'keysCount' => \count($keys),
					'keysSample' => \array_slice($keys, 0, 10),
					'request' => $this->buildRequestInfo($req),
					'origin' => $this->detectInvalidationOrigin(),
					'extension' => $this->extractExtensionContext($trace, $keys),
					'configKeyPlugins' => $this->detectConfigKeyPlugins($keys)
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
					$httpCacheKey = 'http-cache-' . hash('sha256', implode('|', $parts));
					$this->logger->info('HttpCacheGenerateKeyEvent', [
						'uri' => $requestUri,
						'key' => $httpCacheKey,
						'partsCount' => \count($parts),
						'partsSample' => \array_slice($parts, 0, 12),
					]);
					return;
				}
				
				$hash = $event->get('hash');
				$httpCacheKey = 'http-cache-' . $hash;
				
				if ($cookies->has(CacheResponseSubscriber::CONTEXT_CACHE_COOKIE)) {
					$val = $cookies->get(CacheResponseSubscriber::CONTEXT_CACHE_COOKIE);
					$httpCacheKey = 'http-cache-' . hash('sha256', $hash . '-' . $val);
					$this->logger->info('HttpCacheGenerateKeyEvent', [
						'uri' => $requestUri,
						'key' => $httpCacheKey,
						'cookieApplied' => CacheResponseSubscriber::CONTEXT_CACHE_COOKIE
					]);
					return;
				}

				if ($cookies->has(CacheResponseSubscriber::CURRENCY_COOKIE)) {
					$val = $cookies->get(CacheResponseSubscriber::CURRENCY_COOKIE);
					$httpCacheKey = 'http-cache-' . hash('sha256', $hash . '-' . $val);
					$this->logger->info('HttpCacheGenerateKeyEvent', [
						'uri' => $requestUri,
						'key' => $httpCacheKey,
						'cookieApplied' => CacheResponseSubscriber::CURRENCY_COOKIE
					]);
					return;
				}

				if ($attributes->has(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID)) {
					$val = $attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID);
					$httpCacheKey =  'http-cache-' . hash('sha256', $hash . '-' . $val);
					$this->logger->info('HttpCacheGenerateKeyEvent', [
						'uri' => $requestUri,
						'key' => $httpCacheKey,
						'attributeApplied' => SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID
					]);
					return;
				}

				$this->logger->info('HttpCacheGenerateKeyEvent', [
					'uri' => $requestUri,
					'key' => $httpCacheKey,
					'note' => 'no discriminator cookie applied'
				]);
			}
		} catch (\Throwable $th) {
			$this->logger->error($th->getMessage());
		}
	}

	/**
	* Build a snapshot of the request.
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
		$forceHeader = false;

		if (\defined(PlatformRequest::class . '::HEADER_FORCE_CACHE_INVALIDATE')) {
			/** @var string $forceConst */
			$forceConst = \constant(PlatformRequest::class . '::HEADER_FORCE_CACHE_INVALIDATE');
			$forceHeader = $headers->get($forceConst) === '1';
		}

		return [
			'method' => $request->getMethod(),
			'uri' => $request->getUri(),
			'route' => $request->attributes->get('_route'),
			'controller' => $request->attributes->get('_controller'),
			'routeScope' => \is_array($routeScope) ? $routeScope : ($routeScope ? [$routeScope] : []),
			'salesChannelId' => $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID),
			'forceHeader' => $forceHeader,
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
	* Inspect current backtrace to get invalidation trigger.
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
		$sampled = 0;

		foreach ($trace as $frame) {
			$class = $frame['class'] ?? null;
			$function = (string) $frame['function'];
			$file = $frame['file'] ?? null;

			if ($class === 'Shopware\\Core\\Framework\\Adapter\\Cache\\InvalidateCacheTaskHandler' && $function === 'run') {
				$origin['viaScheduledTask'] = true;
			}

			if ($class === 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidator' && $function === 'invalidateExpired') {
				$origin['viaInvalidateExpired'] = true;
			}

			if ($class === 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber' && str_starts_with($function, 'invalidate')) {
				$origin['source'] = $class;
				$origin['method'] = $function;
				break;
			}

			if ($class && $class !== __CLASS__ && $class !== 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidator') {
				if (str_contains($function, 'invalidate')) {
					$origin['source'] = $class;
					$origin['method'] = $function;
				}
			}

			if ($sampled < 3 && ($class || $function || $file)) {
				$origin['framesSample'][] = [
					'class' => $class,
					'function' => $function,
					'file' => $file,
				];

				$sampled++;
			}
		}

		return $origin;
	}

	/**
	* Inspect frames to find most probable extension involved.
	* @param array<int, array<string, mixed>> $trace
	* @param array<int, string>|null $keys
	* @return array<string, mixed>
	*/
	private function extractExtensionContext(array $trace, ?array $keys = null): array
	{
		$counts = [];
		$firstNonCore = null;

		$inc = static function (array $k) use (&$counts): void {
			$key = ($k['type'] ?? 'unknown') . ':' . ($k['name'] ?? '');
			$counts[$key] = ($counts[$key] ?? 0) + 1;
		};

		$keyPlugins = $keys ? $this->detectConfigKeyPlugins($keys) : [];

		foreach ($trace as $frame) {
			$file = $frame['file'] ?? null;
			$class = $frame['class'] ?? null;
			$function = (string) ($frame['function'] ?? '');

			$info = $file ? $this->summarizePathToExtension($file) : null;

			if ($info === null && \is_string($class)) {
				$info = $this->summarizeClassToExtension($class);
			}

			if ($info !== null) {
				$inc($info);

				if ($firstNonCore === null && \in_array($info['name'] ?? '', $keyPlugins, true)) {
					$firstNonCore = $info + ['class' => $class, 'file' => $file, 'function' => $function, 'reason' => 'config-key-match'];
				}

				if ($firstNonCore === null && ($info['type'] ?? '') !== 'shopware-core') {
					$firstNonCore = $info + ['class' => $class, 'file' => $file, 'function' => $function];
				}
			}
		}

		arsort($counts);
		$top = [];

		foreach (array_slice(array_keys($counts), 0, 3) as $key) {
			[$type, $name] = array_pad(explode(':', $key, 2), 2, null);
			$top[] = ['type' => $type, 'name' => $name, 'count' => $counts[$key]];
		}

		return [
			'primary' => $firstNonCore,
			'topCandidates' => $top,
		];
	}

	/**
	* Detect extension names from config keys.
	* @param array<int, string> $keys
	* @return array<int, string>
	*/
	private function detectConfigKeyPlugins(array $keys): array
	{
		$plugins = [];

		foreach ($keys as $k) {
			if (!\is_string($k)) {
				continue;
			}

			if (str_starts_with($k, 'config.')) {
				$rest = substr($k, strlen('config.'));
				$parts = explode('.', $rest, 2);
				$candidate = $parts[0] ?? null;

				if ($candidate) {
					$plugins[$candidate] = true;
				}
			}
		}

		return array_values(array_keys($plugins));
	}

	private function summarizePathToExtension(string $file): ?array
	{
		$marker = '/custom/plugins/';
		$pos = strpos($file, $marker);

		if ($pos !== false) {
			$rest = substr($file, $pos + strlen($marker));
			$parts = explode('/', $rest, 2);
			$plugin = $parts[0] ?? null;

			if ($plugin) {
				return ['type' => 'custom-plugin', 'name' => $plugin];
			}
		}

		$marker = '/custom/static-plugins/';
		$pos = strpos($file, $marker);

		if ($pos !== false) {
			$rest = substr($file, $pos + strlen($marker));
			$parts = explode('/', $rest, 2);
			$plugin = $parts[0] ?? null;

			if ($plugin) {
				return ['type' => 'static-plugin', 'name' => $plugin];
			}
		}

		if (strpos($file, '/vendor/shopware/') !== false) {
			return ['type' => 'shopware-core', 'name' => 'shopware'];
		}

		$marker = '/vendor/';
		$pos = strpos($file, $marker);

		if ($pos !== false) {
			$rest = substr($file, $pos + strlen($marker));
			$parts = explode('/', $rest, 3);
			$vendor = $parts[0] ?? null;
			$package = $parts[1] ?? null;

			if ($vendor && $package) {
				return ['type' => 'vendor-package', 'name' => $vendor . '/' . $package];
			}
		}

		return null;
	}

	private function summarizeClassToExtension(string $class): ?array
	{
		$parts = explode('\\', $class);
		$root = $parts[0] ?? null;

		if ($root === 'Shopware') {
			return ['type' => 'shopware-core', 'name' => 'shopware'];
		}

		if ($root) {
			return ['type' => 'namespace', 'name' => $root];
		}

		return null;
	}
}