<?php declare(strict_types=1);

namespace DigaShopwareCacheHelper\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheIdLoader;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Framework\Cache\CacheWarmer\CacheRouteWarmerRegistry;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;

class DigaHttpCacheWarmUpCommand extends Command
{

    /**
     * @var EntityRepository
     */
    private $salesChannelRepository;

    /**
     * @var CacheIdLoader
     */
    private $cacheIdLoader;

    /**
     * @var MessageBusInterface
     */
    private $bus;

    /**
     * @var CacheRouteWarmerRegistry
     */
    private $registry;
    /**
     * @internal
     */
    public function __construct(
        EntityRepository $salesChannelRepository,
        CacheIdLoader $cacheIdLoader,
        MessageBusInterface $bus,
        CacheRouteWarmerRegistry $registry)
    {
        parent::__construct();
        $this->salesChannelRepository = $salesChannelRepository;
        $this->cacheIdLoader = $cacheIdLoader;
        $this->bus = $bus;
        $this->registry = $registry;
    }

    protected function configure(): void
    {
        $this
            ->setName('diga:http:cache:warmup')
            ->addOption('keep-cache', null, InputOption::VALUE_NONE, 'Keeps the same cache id so no cache invalidation is triggered')    
            ->addArgument('warmer', InputArgument::OPTIONAL, 'which warmer shoud be used? [NavigationRouteWarmer, ProductRouteWarmer]');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheId = null;

        if (!$input->getOption('keep-cache')) {
            $cacheId = Uuid::randomHex();
        }
        
        // $this->warmer->warmUp($cacheId);
        $cacheId = $cacheId ?? $this->cacheIdLoader->load();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('domains');
        $activeSalesChannels = $this->salesChannelRepository->search($criteria, Context::createDefaultContext());

        $activeDomains = [];
        /** @var SalesChannelEntity $activeSalesChannel */
        foreach($activeSalesChannels as $activeSalesChannel) {
            $typeId = $activeSalesChannel->getTypeId();
            if(strtoupper($typeId) != strtoupper('8a243080f92e4c719546314b577cf82b')) {
                continue;
            }   
            /** @var SalesChannelDomainCollection $domains */
            $domains = $activeSalesChannel->getDomains();

            foreach($domains as $domain) {
                array_push($activeDomains, $domain);
            }
        }

        $this->cacheIdLoader->write($cacheId);

        $routeWarmer = $input->getArgument('warmer');

        // generate all message to calculate message count
        $this->createMessages($cacheId, $activeDomains, $routeWarmer);

        return self::SUCCESS;
    }

    /**
     * @param array<SalesChannelDomainEntity> $domains
     */
    private function createMessages(string $cacheId, $domains, string $routeWarmer): void
    {
        /** @var SalesChannelDomainEntity $domain */
        foreach ($domains as $domain) {
            foreach ($this->registry->getWarmers() as $warmer) {

                if(!empty($routeWarmer)) {
                    $parts = explode('\\', get_class($warmer));
                    $warmerClass = array_pop($parts);

                    if($warmerClass !== $routeWarmer) {
                        continue;
                    }
                }

                $message = $warmer->createMessage($domain, null);

                while ($message) {
                    $offset = $message->getOffset();

                    $message->setCacheId($cacheId);
                    $message->setDomain($domain->getUrl());

                    $this->bus->dispatch($message);

                    $message = $warmer->createMessage($domain, $offset);
                }
            }
        }
    }
}
