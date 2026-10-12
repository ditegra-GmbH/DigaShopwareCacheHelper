<?php

declare(strict_types=1);

namespace DigaTestFixture\ErpSync\Service;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;

/**
 * Stands in for third party plugin code that invalidates the cache.
 */
class ProductImporter
{
    public function import(CacheInvalidator $cacheInvalidator, string $productId, bool $force = false): void
    {
        $cacheInvalidator->invalidate(['product-' . $productId, 'product-listing-route'], $force);
    }
}
