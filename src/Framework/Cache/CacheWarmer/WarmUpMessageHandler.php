<?php

declare(strict_types=1);

namespace DigaShopwareCacheHelper\Framework\Cache\CacheWarmer;

use Psr\Log\LoggerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\RouterInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheIdLoader;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Cache\CacheWarmer\WarmUpMessage;

class WarmUpMessageHandler
{
    /**
     * @internal
     */
    public function __construct(
        private readonly LoggerInterface $logger, 
        private readonly SystemConfigService $systemConfigService, 
        private readonly RouterInterface $router, 
        private readonly CacheIdLoader $cacheIdLoader, 
        private readonly Connection $connection)
    {
    }

    public function __invoke($message): void
    {
        $logCacheWarmup = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logCacheWarmup');
        if (!$logCacheWarmup) {
            return;
        }

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
            $domain = $message->getDomain();
            $url = rtrim($domain, '/') . $pathInfo;

            if ($logSeoUrlsOnWarmUp) {
                $salecChannelIds = $this->getSalecChannelId($domain);
                $seoUrlsResults = $this->getSeoUrls($route, $pathInfo);

                $seoUrls = '';
                if (!empty($seoUrlsResults)) {
                    foreach ($seoUrlsResults as $seoPathInfo) {
                        $salesChannelIid = $seoPathInfo['sales_channel_id'];
                        if (!in_array($salesChannelIid, $salecChannelIds)) {
                            continue;
                        }

                        $seoUrls .= '/'. $seoPathInfo['seo_path_info'] . ' ';
                    }
                }
                $this->logger->info('WarmUpMessage handler | ' . $url .' |  | ' .  $seoUrls);
            } else {
                $this->logger->info('WarmUpMessage handler | ' . $url .' |  | ');
            }
        }
    }

    private function getSalecChannelId(string $domain): array
    {
        $sql = sprintf(
            "SELECT LOWER(HEX(`sales_channel_id`)) AS `sales_channel_id` FROM `sales_channel_domain` WHERE `sales_channel_domain`.`url` LIKE '%s';",
            $domain
        );

        $salesChannelIds = [];
        $query = $this->connection->executeQuery($sql);
        while (($row = $query->fetchAssociative()) !== false) {
            array_push($salesChannelIds, $row['sales_channel_id']);
        }
        return $salesChannelIds;
    }

    private function getSeoUrls(string $routeName, string $pathInfo): array
    {
        $sql = 'SELECT seo_path_info, LOWER(HEX(`sales_channel_id`)) AS `sales_channel_id` FROM seo_url 
        WHERE `seo_url`.`route_name` =:routeName
         AND `seo_url`.`path_info` =:pathInfo 
         AND `seo_url`.`is_canonical` = 1
         AND `seo_url`.`is_deleted` = 0 ';

        $query = $this->connection->executeQuery(
            $sql,
            [
                'routeName' => $routeName,
                'pathInfo' => $pathInfo
            ]
        );

        return  $query->fetchAllAssociative();
    }
}
