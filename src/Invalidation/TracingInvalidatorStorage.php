<?php

declare(strict_types=1);

namespace DigaShopwareCacheHelper\Invalidation;

use Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\AbstractInvalidatorStorage;

/**
 * Records the origin of deferred invalidations: CacheInvalidator::invalidate() stores the tags here
 * instead of purging them, the scheduled task purges them later without knowing the caller.
 */
class TracingInvalidatorStorage extends AbstractInvalidatorStorage
{
    public function __construct(
        private readonly AbstractInvalidatorStorage $inner,
        private readonly InvalidationOriginRecorder $recorder,
    ) {
    }

    /**
     * @param array<string> $tags
     */
    public function store(array $tags): void
    {
        // record only after storing succeeded, otherwise Shopware purges immediately and the event listener records it
        $this->inner->store($tags);

        if ($this->recorder->isEnabled()) {
            $this->recorder->record($tags, InvalidationOriginRecorder::MODE_DEFERRED);
        }
    }

    /**
     * @return list<string>
     */
    public function loadAndDelete(): array
    {
        return $this->inner->loadAndDelete();
    }
}
