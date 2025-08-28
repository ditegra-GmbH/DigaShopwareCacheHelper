<?php

declare(strict_types=1);

namespace DigaShopwareCacheHelper\Decorator;

use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use DigaShopwareCacheHelper\Service\DigaLoggerFactoryService;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber as CoreCacheInvalidationSubscriber;

class LoggingCacheInvalidationSubscriber
{
	public function __construct(
		private readonly CoreCacheInvalidationSubscriber $inner,
		private readonly DigaLoggerFactoryService $logger,
		private readonly RequestStack $requestStack
	) {}

	/**
	* Forwarder: logs calls and delegates to inner subscriber.
	* @param string $name
	* @param array<int, mixed> $arguments
	*/
	public function __call(string $name, array $arguments)
	{
		if (\str_starts_with($name, 'invalidate')) {
			try {
				$trace = \debug_backtrace(0, 40);
				$req = $this->requestStack->getMainRequest();
				$ext = $this->extractExtensionContext($trace);
				$this->logger->info($name, [
					'args' => $this->summarizeArgs($arguments),
					'request' => $this->buildRequestInfo($req),
					'extensionPrimary' => $ext['primary'] ?? null,
					'extensionTop' => $ext['topCandidates'] ?? []
				]);
			} catch (\Throwable $e) {}
		}

		return $this->inner->$name(...$arguments);
	}

	/**
	* @param array<int, mixed> $args
	* @return array<int, mixed>
	*/
	private function summarizeArgs(array $args): array
	{
		$out = [];

		foreach ($args as $arg) {
			try {
				if (\is_object($arg)) {
					$out[] = ['type' => \get_class($arg)];
				} elseif (\is_array($arg)) {
					$out[] = ['type' => 'array', 'keys' => array_slice(array_keys($arg), 0, 5)];
				} elseif (\is_scalar($arg) || $arg === null) {
					$out[] = ['type' => \gettype($arg), 'value' => $arg];
				} else {
					$out[] = ['type' => \gettype($arg)];
				}
			} catch (\Throwable $e) {
				$out[] = ['type' => 'unknown-error'];
			}
		}

		return $out;
	}

	/**
	* Build snapshot of current request.
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
			'ip' => $request->getClientIp()
		];
	}

	/**
	* Inspect frames to find the most probable extension.
	* @param array<int, array<string, mixed>> $trace
	* @return array<string, mixed>
	*/
	private function extractExtensionContext(array $trace): array
	{
		$counts = [];
		$firstNonCore = null;
		$inc = static function (array $k) use (&$counts): void {
			$key = ($k['type'] ?? 'unknown') . ':' . ($k['name'] ?? '');
			$counts[$key] = ($counts[$key] ?? 0) + 1;
		};

		foreach ($trace as $frame) {
			$file = $frame['file'] ?? null;
			$class = $frame['class'] ?? null;
			$info = $file ? $this->summarizePathToExtension($file) : null;

			if ($info === null && \is_string($class)) {
				$info = $this->summarizeClassToExtension($class);
			}

			if ($info !== null) {
				$inc($info);

				if ($firstNonCore === null && ($info['type'] ?? '') !== 'shopware-core') {
					$firstNonCore = $info + ['class' => $class, 'file' => $file];
				}
			}
		}

		\arsort($counts);
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
		$parts = explode('\\\\', $class);
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