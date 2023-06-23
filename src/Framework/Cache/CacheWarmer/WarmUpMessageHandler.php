<?php declare(strict_types=1);

namespace DigaShopwareCacheHelper\Framework\Cache\CacheWarmer;

use Psr\Log\LoggerInterface;
use Doctrine\DBAL\Connection;
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

    private Connection $connection;

    /**
     * @internal
     */
    public function __construct(
        LoggerInterface $logger,
        SystemConfigService $systemConfigService,
        RouterInterface $router,
        CacheIdLoader $cacheIdLoader,
        Connection $connection,
    ) {        
        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
        $this->router = $router;        
        $this->cacheIdLoader = $cacheIdLoader;
        $this->connection = $connection;    
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
            $this->logger->info('Skip WarmUp because: ' . $this->cacheIdLoader->load() . ' != ' . $message->getCacheId(), [$message]);
            return;
        }
        
        $logSeoUrlsOnWarmUp = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logSeoUrlsOnWarmUp');
        
        foreach ($message->getParameters() as $parameters) {
            
            $route = $message->getRoute();
            $pathInfo = $this->router->generate($route, $parameters);
            $url = rtrim($message->getDomain(), '/') . $pathInfo;

            if($logSeoUrlsOnWarmUp){
                $seoUrls[] = $this->getSeoUrls($route, $pathInfo);

                $this->logger->info('WarmUp url: ' . $url, $seoUrls);

            } else {

                $this->logger->info('WarmUp url: ' . $url, []);                
            }            
        }
    }

    private function getSeoUrls(string $routeName, string $pathInfo) : array{

        $sql = 'SELECT seo_path_info FROM seo_url 
        WHERE `seo_url`.`route_name` =:routeName
         AND `seo_url`.`path_info` =:pathInfo 
         AND `seo_url`.`is_canonical` = 1
         AND `seo_url`.`is_deleted` = 0 ';
        
        return $this->connection->fetchAll(
            $sql,
            [
                'routeName' => $routeName,
                'pathInfo' => $pathInfo
            ]
        );
    }
}
