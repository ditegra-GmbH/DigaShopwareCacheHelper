<?php

declare(strict_types=1);

namespace DigaShopwareCacheHelper\Invalidation;

use DigaShopwareCacheHelper\Service\DigaLoggerFactoryService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Collects invalidation origins in memory, aggregated per request / command / worker message,
 * and writes one log line per origin on flush().
 */
class InvalidationOriginRecorder implements ResetInterface
{
    public const MODE_IMMEDIATE = 'immediate';
    public const MODE_DEFERRED = 'deferred';

    private const CONFIG_PREFIX = 'DigaShopwareCacheHelper.config.';

    // flush early so a long running import cannot grow the buffer without bounds
    private const MAX_BUFFERED_ORIGINS = 200;

    /**
     * @var array{enabled: bool, logStack: bool, maxTags: int, fullUri: bool}|null
     */
    private ?array $settings = null;

    private ?string $context = null;

    /**
     * @var array<string, array{origin: InvalidationOrigin, mode: string, calls: int, tagCount: int, tags: array<string, true>, truncated: bool}>
     */
    private array $buffer = [];

    private bool $flushing = false;

    /**
     * @param \Closure(): DigaLoggerFactoryService $loggerFactory resolved on first flush only
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly InvalidationOriginResolver $resolver,
        private readonly \Closure $loggerFactory,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->settings()['enabled'];
    }

    /**
     * @param array<string> $tags
     * @param self::MODE_* $mode
     */
    public function record(array $tags, string $mode): void
    {
        try {
            $tags = array_filter(array_unique($tags));
            if ($tags === []) {
                return;
            }

            $settings = $this->settings();
            $origin = $this->resolver->resolve($settings['logStack']);
            if ($origin === null) {
                return;
            }

            // the main request is already gone at kernel.terminate, so capture it while it is still on the stack
            $this->context ??= $this->contextFromRequest($settings['fullUri']);

            $key = implode('|', [$origin->plugin, $origin->trigger, $origin->caller, $origin->callerLocation, $mode, implode('>', $origin->pluginStack)]);

            if (!isset($this->buffer[$key])) {
                $this->buffer[$key] = ['origin' => $origin, 'mode' => $mode, 'calls' => 0, 'tagCount' => 0, 'tags' => [], 'truncated' => false];
            }

            $entry = &$this->buffer[$key];
            ++$entry['calls'];
            $entry['tagCount'] += \count($tags);

            $maxTags = $settings['maxTags'];
            foreach ($tags as $tag) {
                if (isset($entry['tags'][$tag])) {
                    continue;
                }
                if (\count($entry['tags']) >= $maxTags) {
                    $entry['truncated'] = true;
                    break;
                }
                $entry['tags'][$tag] = true;
            }
            unset($entry);

            if (\count($this->buffer) >= self::MAX_BUFFERED_ORIGINS) {
                $this->flush();
            }
        } catch (\Throwable) {
            // logging must never break a cache invalidation
        }
    }

    public function setContext(?string $context): void
    {
        $this->context = $context;
    }

    public function flush(): void
    {
        if ($this->buffer === [] || $this->flushing) {
            return;
        }

        $this->flushing = true;

        try {
            $logger = ($this->loggerFactory)();
            $context = $this->context ?? 'unknown';

            foreach ($this->buffer as $entry) {
                $logger->info($this->formatLine($context, $entry));
            }
        } catch (\Throwable) {
            // logging must never break the request lifecycle
        } finally {
            $this->buffer = [];
            $this->flushing = false;
        }
    }

    public function reset(): void
    {
        $this->flush();
        $this->settings = null;
        $this->context = null;
    }

    /**
     * Read once per request / message, so invalidate() never hits the SystemConfigService repeatedly.
     *
     * @return array{enabled: bool, logStack: bool, maxTags: int, fullUri: bool}
     */
    private function settings(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        // guard against re-entry: reading the config must never end up recording itself
        $this->settings = ['enabled' => false, 'logStack' => false, 'maxTags' => 20, 'fullUri' => false];

        try {
            $maxTags = $this->systemConfigService->get(self::CONFIG_PREFIX . 'invalidationOriginMaxTags');

            $this->settings = [
                'enabled' => (bool) $this->systemConfigService->get(self::CONFIG_PREFIX . 'logInvalidationOrigin'),
                'logStack' => (bool) $this->systemConfigService->get(self::CONFIG_PREFIX . 'invalidationOriginLogStack'),
                'maxTags' => is_numeric($maxTags) ? max(0, (int) $maxTags) : 20,
                'fullUri' => (bool) $this->systemConfigService->get(self::CONFIG_PREFIX . 'logFullUri'),
            ];
        } catch (\Throwable) {
            // keep the feature disabled if the config cannot be read, e.g. during plugin installation
        }

        return $this->settings;
    }

    /**
     * @param array{origin: InvalidationOrigin, mode: string, calls: int, tagCount: int, tags: array<string, true>, truncated: bool} $entry
     */
    private function formatLine(string $context, array $entry): string
    {
        $origin = $entry['origin'];
        $tags = array_keys($entry['tags']);

        $tagInfo = (string) $entry['tagCount'];
        if ($entry['truncated']) {
            $tagInfo .= ' (first ' . \count($tags) . ')';
        }

        $line = 'InvalidationOrigin | ' . $context . ' | ' . $origin->plugin
            . ' | mode: ' . $entry['mode']
            . ' | calls: ' . $entry['calls']
            . ' | tags: ' . $tagInfo . ' ' . json_encode($tags, \JSON_UNESCAPED_SLASHES)
            . ' | trigger: ' . ($origin->trigger ?? '-');

        if ($origin->caller !== null) {
            $line .= ' | caller: ' . $origin->caller;
            if ($origin->callerLocation !== null) {
                $line .= ' (' . $origin->callerLocation . ')';
            }
        }

        if ($origin->pluginStack !== []) {
            $line .= ' | stack: ' . implode(' < ', $origin->pluginStack);
        }

        return $line;
    }

    private function contextFromRequest(bool $fullUri): ?string
    {
        $request = $this->requestStack->getMainRequest();
        if ($request instanceof Request) {
            return $request->getMethod() . ' ' . ($fullUri ? $request->getUri() : $request->getRequestUri());
        }

        if (\PHP_SAPI === 'cli' && isset($_SERVER['argv'][1]) && \is_string($_SERVER['argv'][1])) {
            return 'cli: ' . $_SERVER['argv'][1];
        }

        return null;
    }
}
