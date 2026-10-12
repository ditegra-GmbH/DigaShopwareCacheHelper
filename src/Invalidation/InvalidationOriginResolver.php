<?php

declare(strict_types=1);

namespace DigaShopwareCacheHelper\Invalidation;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;

/**
 * Walks the call stack to find which plugin caused a cache invalidation and where it was triggered.
 *
 * Both hooks (InvalidatorStorage::store() and the InvalidateCacheEvent) run inside CacheInvalidator,
 * so the stack always contains CacheInvalidator::invalidate() or ::invalidateExpired().
 * That frame is the anchor: everything above it is the caller side.
 */
class InvalidationOriginResolver
{
    public const MAX_DEPTH = 50;

    private const OWN_NAMESPACE = 'DigaShopwareCacheHelper\\';

    public function __construct(private readonly PluginNamespaceMap $pluginNamespaceMap)
    {
    }

    /**
     * @return InvalidationOrigin|null null for the scheduled purge of deferred tags, whose origin was logged when they were stored
     */
    public function resolve(bool $collectPluginStack = false): ?InvalidationOrigin
    {
        return $this->resolveFrames(
            debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, self::MAX_DEPTH),
            $collectPluginStack
        );
    }

    /**
     * Frame semantics of debug_backtrace(): "class"/"function" is the function executing in that frame,
     * "file"/"line" is where that function was called, i.e. a location inside the next outer frame.
     *
     * @param array<int, array<string, mixed>> $frames innermost first
     */
    public function resolveFrames(array $frames, bool $collectPluginStack = false): ?InvalidationOrigin
    {
        $start = 0;

        foreach ($frames as $index => $frame) {
            if (($frame['class'] ?? null) !== CacheInvalidator::class) {
                continue;
            }

            if (($frame['function'] ?? null) === 'invalidateExpired') {
                return null;
            }

            if (($frame['function'] ?? null) === 'invalidate') {
                $start = $index + 1;

                break;
            }
        }

        $trigger = null;
        $plugin = null;
        $caller = null;
        $callerLocation = null;
        $pluginStack = [];

        foreach (\array_slice($frames, $start, null, true) as $index => $frame) {
            $class = $frame['class'] ?? null;
            if (!\is_string($class) || str_starts_with($class, self::OWN_NAMESPACE)) {
                continue;
            }

            $method = $class . '::' . ($frame['function'] ?? '');
            $trigger ??= $method;

            $pluginName = $this->pluginNamespaceMap->pluginFor($class);
            if ($pluginName === null) {
                continue;
            }

            if ($plugin === null) {
                $plugin = $pluginName;
                $caller = $method;
                $callerLocation = $this->locationInside($frames[$index - 1] ?? []);
            }

            if (!$collectPluginStack) {
                break;
            }

            $pluginStack[] = $method;
        }

        return new InvalidationOrigin(
            $plugin ?? InvalidationOrigin::CORE,
            $trigger,
            $caller,
            $callerLocation,
            $pluginStack
        );
    }

    /**
     * @param array<string, mixed> $innerFrame
     */
    private function locationInside(array $innerFrame): ?string
    {
        if (!isset($innerFrame['file'])) {
            return null;
        }

        return basename((string) $innerFrame['file']) . ':' . ($innerFrame['line'] ?? '?');
    }
}
