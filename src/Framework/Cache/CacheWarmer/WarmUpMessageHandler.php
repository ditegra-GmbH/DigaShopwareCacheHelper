<?php declare(strict_types=1);

namespace DigaShopwareCacheHelper\Framework\Cache\CacheWarmer;

use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheIdLoader;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Cache\CacheWarmer\WarmUpMessage;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;

class WarmUpMessageHandler extends AbstractMessageHandler
{
    /**
    * @var LoggerInterface
    */
    private $logger;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var CacheIdLoader
     */
    private $cacheIdLoader;

    /**
     * @internal
     */
    public function __construct(
        LoggerInterface $logger,
        SystemConfigService $systemConfigService,
        RouterInterface $router,
        CacheIdLoader $cacheIdLoader,

    ) {        
        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
        $this->router = $router;        
        $this->cacheIdLoader = $cacheIdLoader;        
    }

    public static function getHandledMessages(): iterable
    {
        return [WarmUpMessage::class];
    }

    public function handle($message): void
    {
        $logCacheWarmup = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logCacheWarmup');
        if(!$logCacheWarmup)
            return;
        
        if (!$message instanceof WarmUpMessage) {
            return;
        }

        if ($this->cacheIdLoader->load() !== $message->getCacheId()) {
            return;
        }

        foreach ($message->getParameters() as $parameters) {
            
            $url = rtrim($message->getDomain(), '/') . $this->router->generate($message->getRoute(), $parameters);

            $this->logger->info('WarmUp url: ' . $url );
        }
    }

}
