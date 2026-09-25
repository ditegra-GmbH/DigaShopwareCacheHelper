<?php

declare(strict_types=1);

namespace DigaShopwareCacheHelper\Tests\Unit\Invalidation;

use DigaShopwareCacheHelper\DigaShopwareCacheHelper;
use DigaShopwareCacheHelper\Invalidation\PluginNamespaceMap;
use DigaTestFixture\ErpSync\Addon\ErpSyncAddon;
use DigaTestFixture\ErpSync\ErpSync;
use DigaTestFixture\Inactive\InactivePlugin;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;

class PluginNamespaceMapTest extends TestCase
{
    private PluginNamespaceMap $map;

    protected function setUp(): void
    {
        $this->map = new PluginNamespaceMap(new KernelPluginCollection([
            ErpSync::class => new ErpSync(true, '/plugins/ErpSync'),
            ErpSyncAddon::class => new ErpSyncAddon(true, '/plugins/ErpSyncAddon'),
            InactivePlugin::class => new InactivePlugin(false, '/plugins/InactivePlugin'),
            DigaShopwareCacheHelper::class => new DigaShopwareCacheHelper(true, '/plugins/DigaShopwareCacheHelper'),
        ]));
    }

    public function testResolvesClassToPluginByNamespace(): void
    {
        static::assertSame('ErpSync', $this->map->pluginFor('DigaTestFixture\ErpSync\Service\ProductImporter'));
        static::assertSame('DigaShopwareCacheHelper', $this->map->pluginFor('DigaShopwareCacheHelper\Subscriber\CacheEventsSubscriber'));
    }

    public function testLongestNamespacePrefixWins(): void
    {
        static::assertSame('ErpSyncAddon', $this->map->pluginFor('DigaTestFixture\ErpSync\Addon\Service\Foo'));
    }

    public function testIgnoresInactivePluginsAndForeignClasses(): void
    {
        static::assertNull($this->map->pluginFor('DigaTestFixture\Inactive\Service\Foo'));
        static::assertNull($this->map->pluginFor('Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber'));
        // a namespace that only shares the text prefix is a different vendor
        static::assertNull($this->map->pluginFor('DigaTestFixture\ErpSyncOther\Foo'));
    }
}
