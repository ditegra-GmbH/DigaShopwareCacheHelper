<?php

declare(strict_types=1);

namespace DigaShopwareCacheHelper\Tests\Unit\Invalidation;

use DigaShopwareCacheHelper\Invalidation\InvalidationOriginRecorder;
use DigaShopwareCacheHelper\Invalidation\InvalidationOriginResolver;
use DigaShopwareCacheHelper\Invalidation\PluginNamespaceMap;
use DigaShopwareCacheHelper\Invalidation\TracingInvalidatorStorage;
use DigaShopwareCacheHelper\Service\DigaLoggerFactoryService;
use DigaShopwareCacheHelper\Subscriber\InvalidationOriginSubscriber;
use DigaTestFixture\ErpSync\ErpSync;
use DigaTestFixture\ErpSync\Service\ProductImporter;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\AbstractInvalidatorStorage;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Util\Backtrace\BacktraceCollector;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * Runs a real CacheInvalidator with the storage decorator and the event subscriber wired in,
 * so the stack frames are exactly what Shopware produces.
 */
class InvalidationOriginRecordingTest extends TestCase
{
    private AbstractInvalidatorStorage&MockObject $innerStorage;

    private TestHandler $logHandler;

    private RequestStack $requestStack;

    private InvalidationOriginRecorder $recorder;

    private InvalidationOriginSubscriber $subscriber;

    private CacheInvalidator $cacheInvalidator;

    /**
     * @var array<string, mixed>
     */
    private array $config = [
        'DigaShopwareCacheHelper.config.loggingOutput' => 'shopwarelog',
        'DigaShopwareCacheHelper.config.logInvalidationOrigin' => true,
        'DigaShopwareCacheHelper.config.invalidationOriginMaxTags' => 20,
        'DigaShopwareCacheHelper.config.invalidationOriginLogStack' => false,
        'DigaShopwareCacheHelper.config.logFullUri' => false,
    ];

    private int $loggerCreations = 0;

    protected function setUp(): void
    {
        $this->innerStorage = $this->createMock(AbstractInvalidatorStorage::class);
        $this->logHandler = new TestHandler();
        $this->requestStack = new RequestStack();
    }

    public function testDeferredInvalidationsAreAggregatedWithPluginTriggerAndCaller(): void
    {
        $this->boot();
        $this->recorder->setContext('cli: erp:import');
        $this->innerStorage->expects(static::exactly(3))->method('store');

        $importer = new ProductImporter();
        foreach (['p1', 'p2', 'p3'] as $productId) {
            $importer->import($this->cacheInvalidator, $productId);
        }

        static::assertSame([], $this->logHandler->getRecords(), 'nothing is written before flush');

        $this->subscriber->flush();

        static::assertMatchesRegularExpression(
            '/^InvalidationOrigin \| cli: erp:import \| ErpSync \| mode: deferred \| calls: 3 \| tags: 6 '
            . preg_quote('["product-p1","product-listing-route","product-p2","product-p3"]', '/')
            . ' \| trigger: ' . preg_quote(ProductImporter::class, '/') . '::import'
            . ' \| caller: ' . preg_quote(ProductImporter::class, '/') . '::import \(ProductImporter\.php:\d+\)$/',
            $this->onlyMessage()
        );
    }

    public function testForcedInvalidationIsRecordedAsImmediate(): void
    {
        $this->boot();
        $this->innerStorage->expects(static::never())->method('store');

        (new ProductImporter())->import($this->cacheInvalidator, 'p1', true);
        $this->subscriber->flush();

        static::assertStringContainsString('| ErpSync | mode: immediate | calls: 1 |', $this->onlyMessage());
    }

    public function testRequestContextAndCoreOrigin(): void
    {
        $this->requestStack->push(Request::create('/api/_action/system-config/batch', 'POST'));
        $this->boot();

        $this->cacheInvalidator->invalidate(['system-config'], true);
        $this->subscriber->flush();

        static::assertStringStartsWith(
            'InvalidationOrigin | POST /api/_action/system-config/batch | core | mode: immediate | calls: 1 | tags: 1 ["system-config"] | trigger: ',
            $this->onlyMessage()
        );
    }

    public function testScheduledPurgeOfDeferredTagsIsNotRecordedAgain(): void
    {
        $this->boot();
        $this->innerStorage->method('loadAndDelete')->willReturn(['product-p1']);

        static::assertSame(['product-p1'], $this->cacheInvalidator->invalidateExpired());
        $this->subscriber->flush();

        static::assertSame([], $this->logHandler->getRecords());
    }

    public function testFailedStoreIsRecordedOnlyAsImmediatePurge(): void
    {
        $this->boot();
        $this->innerStorage->method('store')->willThrowException(new \RuntimeException('redis down'));

        (new ProductImporter())->import($this->cacheInvalidator, 'p1');
        $this->subscriber->flush();

        static::assertStringContainsString('| ErpSync | mode: immediate |', $this->onlyMessage());
    }

    public function testNothingIsRecordedWhenDisabled(): void
    {
        $this->config['DigaShopwareCacheHelper.config.logInvalidationOrigin'] = false;
        $this->boot();
        $this->innerStorage->expects(static::once())->method('store')->with(['product-p1', 'product-listing-route']);

        (new ProductImporter())->import($this->cacheInvalidator, 'p1');
        (new ProductImporter())->import($this->cacheInvalidator, 'p2', true);
        $this->subscriber->flush();

        static::assertSame([], $this->logHandler->getRecords());
        static::assertSame(0, $this->loggerCreations, 'logger is not created when nothing is recorded');
    }

    public function testTagListIsCapped(): void
    {
        $this->config['DigaShopwareCacheHelper.config.invalidationOriginMaxTags'] = 1;
        $this->boot();

        (new ProductImporter())->import($this->cacheInvalidator, 'p1');
        $this->subscriber->flush();

        static::assertStringContainsString('| tags: 2 (first 1) ["product-p1"] |', $this->onlyMessage());
    }

    public function testLogsPluginStackWhenEnabled(): void
    {
        $this->config['DigaShopwareCacheHelper.config.invalidationOriginLogStack'] = true;
        $this->boot();

        (new ProductImporter())->import($this->cacheInvalidator, 'p1');
        $this->subscriber->flush();

        static::assertStringEndsWith('| stack: ' . ProductImporter::class . '::import', $this->onlyMessage());
    }

    public function testWorkerMessageSetsContext(): void
    {
        $this->boot();

        $this->subscriber->onWorkerMessageReceived(new WorkerMessageReceivedEvent(new Envelope(new \stdClass()), 'async'));
        (new ProductImporter())->import($this->cacheInvalidator, 'p1');
        $this->subscriber->flush();

        static::assertStringStartsWith('InvalidationOrigin | worker: stdClass | ErpSync |', $this->onlyMessage());
    }

    private function onlyMessage(): string
    {
        $records = $this->logHandler->getRecords();
        static::assertCount(1, $records);

        return $records[0]['message'];
    }

    private function boot(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willReturnCallback(fn (string $key) => $this->config[$key] ?? null);

        $logger = new Logger('test', [$this->logHandler]);

        $this->recorder = new InvalidationOriginRecorder(
            $systemConfig,
            new InvalidationOriginResolver(new PluginNamespaceMap(new KernelPluginCollection([
                ErpSync::class => new ErpSync(true, '/plugins/ErpSync'),
            ]))),
            function () use ($logger, $systemConfig): DigaLoggerFactoryService {
                ++$this->loggerCreations;

                return new DigaLoggerFactoryService($logger, $systemConfig, '/dev/null');
            },
            $this->requestStack
        );

        $this->subscriber = new InvalidationOriginSubscriber($this->recorder);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($this->subscriber);

        $this->cacheInvalidator = new CacheInvalidator(
            [],
            new TracingInvalidatorStorage($this->innerStorage, $this->recorder),
            $dispatcher,
            new NullLogger(),
            $this->requestStack,
            new TagAwareAdapter(new ArrayAdapter()),
            false,
            true,
            false,
            new BacktraceCollector(),
            new NativeClock(),
        );
    }
}
