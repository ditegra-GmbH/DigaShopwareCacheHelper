<?php

declare(strict_types=1);

namespace DigaShopwareCacheHelper\Invalidation;

class InvalidationOrigin
{
    public const CORE = 'core';

    /**
     * @param string $plugin name of the causing plugin, or "core" if no plugin frame was found
     * @param string|null $trigger direct caller of CacheInvalidator::invalidate() as Class::method
     * @param string|null $caller plugin method that caused the invalidation as Class::method
     * @param string|null $callerLocation file:line of the call inside $caller
     * @param array<string> $pluginStack all plugin frames, innermost first (only filled when requested)
     */
    public function __construct(
        public readonly string $plugin,
        public readonly ?string $trigger,
        public readonly ?string $caller,
        public readonly ?string $callerLocation,
        public readonly array $pluginStack = [],
    ) {
    }
}
