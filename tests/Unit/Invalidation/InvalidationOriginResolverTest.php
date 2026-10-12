<?php

declare(strict_types=1);

namespace DigaShopwareCacheHelper\Tests\Unit\Invalidation;

use DigaShopwareCacheHelper\Invalidation\InvalidationOrigin;
use DigaShopwareCacheHelper\Invalidation\InvalidationOriginResolver;
use DigaShopwareCacheHelper\Invalidation\PluginNamespaceMap;
use DigaTestFixture\ErpSync\ErpSync;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;

class InvalidationOriginResolverTest extends TestCase
{
    private InvalidationOriginResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new InvalidationOriginResolver(new PluginNamespaceMap(new KernelPluginCollection([
            ErpSync::class => new ErpSync(true, '/plugins/ErpSync'),
        ])));
    }

    public function testFindsTriggerAndFirstPluginFrameBehindCoreSubscriber(): void
    {
        $origin = $this->resolver->resolveFrames($this->framesOfPluginWriteThroughDal());

        static::assertSame('ErpSync', $origin->plugin);
        static::assertSame('Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber::invalidateManufacturerFilters', $origin->trigger);
        static::assertSame('DigaTestFixture\ErpSync\Service\ProductImporter::importBatch', $origin->caller);
        static::assertSame('ProductImporter.php:88', $origin->callerLocation);
        static::assertSame([], $origin->pluginStack);
    }

    public function testCollectsAllPluginFramesWhenRequested(): void
    {
        $origin = $this->resolver->resolveFrames($this->framesOfPluginWriteThroughDal(), true);

        static::assertSame([
            'DigaTestFixture\ErpSync\Service\ProductImporter::importBatch',
            'DigaTestFixture\ErpSync\Command\ImportCommand::execute',
        ], $origin->pluginStack);
    }

    public function testFallsBackToCoreWithoutPluginFrame(): void
    {
        $origin = $this->resolver->resolveFrames([
            ['class' => 'Symfony\Component\EventDispatcher\EventDispatcher', 'function' => 'dispatch', 'file' => '/core/CacheInvalidator.php', 'line' => 86],
            ['class' => CacheInvalidator::class, 'function' => 'purge', 'file' => '/core/CacheInvalidator.php', 'line' => 50],
            ['class' => CacheInvalidator::class, 'function' => 'invalidate', 'file' => '/core/CacheInvalidationSubscriber.php', 'line' => 100],
            ['class' => 'Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber', 'function' => 'invalidateConfig', 'file' => '/vendor/EventDispatcher.php', 'line' => 10],
            ['function' => 'call_user_func'],
        ]);

        static::assertSame(InvalidationOrigin::CORE, $origin->plugin);
        static::assertSame('Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber::invalidateConfig', $origin->trigger);
        static::assertNull($origin->caller);
        static::assertNull($origin->callerLocation);
    }

    public function testSkipsScheduledPurgeOfDeferredTags(): void
    {
        static::assertNull($this->resolver->resolveFrames([
            ['class' => 'Symfony\Component\EventDispatcher\EventDispatcher', 'function' => 'dispatch', 'file' => '/core/CacheInvalidator.php', 'line' => 86],
            ['class' => CacheInvalidator::class, 'function' => 'purge', 'file' => '/core/CacheInvalidator.php', 'line' => 70],
            ['class' => CacheInvalidator::class, 'function' => 'invalidateExpired', 'file' => '/core/InvalidateCacheTaskHandler.php', 'line' => 29],
            ['class' => 'Shopware\Core\Framework\Adapter\Cache\InvalidateCacheTaskHandler', 'function' => 'run'],
        ]));
    }

    public function testPluginCallingInvalidateDirectlyIsTriggerAndCaller(): void
    {
        $origin = $this->resolver->resolveFrames([
            ['class' => CacheInvalidator::class, 'function' => 'invalidate', 'file' => '/plugins/ErpSync/src/Service/CacheWarmer.php', 'line' => 42],
            ['class' => 'DigaTestFixture\ErpSync\Service\CacheWarmer', 'function' => 'warm', 'file' => '/plugins/ErpSync/src/Command/WarmCommand.php', 'line' => 7],
        ]);

        static::assertSame('ErpSync', $origin->plugin);
        static::assertSame('DigaTestFixture\ErpSync\Service\CacheWarmer::warm', $origin->trigger);
        static::assertSame('DigaTestFixture\ErpSync\Service\CacheWarmer::warm', $origin->caller);
        static::assertSame('CacheWarmer.php:42', $origin->callerLocation);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function framesOfPluginWriteThroughDal(): array
    {
        return [
            ['class' => 'DigaShopwareCacheHelper\Invalidation\InvalidationOriginResolver', 'function' => 'resolve', 'file' => '/plugins/Diga/src/Invalidation/InvalidationOriginRecorder.php', 'line' => 1],
            ['class' => 'DigaShopwareCacheHelper\Invalidation\InvalidationOriginRecorder', 'function' => 'record', 'file' => '/plugins/Diga/src/Invalidation/TracingInvalidatorStorage.php', 'line' => 1],
            ['class' => 'DigaShopwareCacheHelper\Invalidation\TracingInvalidatorStorage', 'function' => 'store', 'file' => '/core/CacheInvalidator.php', 'line' => 66],
            ['class' => CacheInvalidator::class, 'function' => 'invalidate', 'file' => '/core/CacheInvalidationSubscriber.php', 'line' => 349],
            ['class' => 'Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber', 'function' => 'invalidateManufacturerFilters', 'file' => '/vendor/EventDispatcher.php', 'line' => 206],
            ['class' => 'Symfony\Component\EventDispatcher\EventDispatcher', 'function' => 'callListeners', 'file' => '/core/EntityRepository.php', 'line' => 50],
            ['class' => 'Shopware\Core\Framework\DataAbstractionLayer\EntityRepository', 'function' => 'upsert', 'file' => '/plugins/ErpSync/src/Service/ProductImporter.php', 'line' => 88],
            ['class' => 'DigaTestFixture\ErpSync\Service\ProductImporter', 'function' => 'importBatch', 'file' => '/plugins/ErpSync/src/Command/ImportCommand.php', 'line' => 30],
            ['class' => 'DigaTestFixture\ErpSync\Command\ImportCommand', 'function' => 'execute', 'file' => '/vendor/Command.php', 'line' => 300],
            ['class' => 'Symfony\Component\Console\Command\Command', 'function' => 'run'],
        ];
    }
}
