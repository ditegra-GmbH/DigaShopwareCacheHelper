<?php

declare(strict_types=1);

namespace DigaShopwareCacheHelper\Decorator;

use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use DigaShopwareCacheHelper\Service\DigaLoggerFactoryService;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator as CoreCacheInvalidator;

class LoggingCacheInvalidator
{
	public function __construct(
	private readonly CoreCacheInvalidator $inner,
	private readonly DigaLoggerFactoryService $logger,
	private readonly RequestStack $requestStack,
) {}

	/**
	* @param array<string> $tags
	*/
	public function invalidate(array $tags, bool $force = false): void
	{
		$request = $this->requestStack->getMainRequest();
		$trace = \debug_backtrace(0, 50);
		$start = \microtime(true);
		$this->inner->invalidate($tags, $force);
		$duration = (int) \round((\microtime(true) - $start) * 1000);

		try {
			$caller = $this->detectCallerFromTrace($trace);
			$this->logger->info('CacheInvalidator.invalidate', [
				'tagsCount' => \count($tags),
				'tagsSample' => \array_slice($tags, 0, 10),
				'force' => $force,
				'durationMs' => $duration,
				'request' => $this->buildRequestInfo($request),
				'callerSource' => $caller['source'] ?? null,
				'callerMethod' => $caller['method'] ?? null,
				'framesSample' => \array_slice($caller['frames'] ?? [], 0, 3)
			]);
		} catch (\Throwable) {
		}
	}

	public function invalidateExpired(): void
	{
		$request = $this->requestStack->getMainRequest();
		$trace = \debug_backtrace(0, 40);
		$start = \microtime(true);
		$this->inner->invalidateExpired();
		$duration = (int) \round((\microtime(true) - $start) * 1000);

		try {
			$caller = $this->detectCallerFromTrace($trace);
			$this->logger->info('CacheInvalidator.invalidateExpired', [
				'durationMs' => $duration,
				'request' => $this->buildRequestInfo($request),
				'callerSource' => $caller['source'] ?? null,
				'callerMethod' => $caller['method'] ?? null,
				'framesSample' => \array_slice($caller['frames'] ?? [], 0, 3)
			]);
		} catch (\Throwable) {}
	}

	/**
	* @return array<string, mixed>
	*/
	private function buildRequestInfo(?Request $request): array
	{
		if (!$request) {
			return ['cli' => \PHP_SAPI === 'cli'];
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
			'userAgent' => $headers->get('user-agent'),
			'ip' => $request->getClientIp()
		];
	}

	/**
	* @param array<int, array<string, mixed>> $trace
	* @return array<string, mixed>
	*/
	private function detectCallerFromTrace(array $trace): array
	{
		$caller = [
			'source' => null,
			'method' => null,
			'frames' => [],
		];

		foreach ($trace as $frame) {
			$class = $frame['class'] ?? null;
			$function = $frame['function'] ?? null;
			$file = $frame['file'] ?? null;

			if ($class && $function && $class !== __CLASS__) {
				if (\str_contains((string) $function, 'invalidate')) {
					$caller['source'] = $class;
					$caller['method'] = $function;
				}
			}

			if ($class || $function || $file) {
				$caller['frames'][] = [
					'class' => $class,
					'function' => $function,
					'file' => $file
				];
			}
		}

		return $caller;
	}
}