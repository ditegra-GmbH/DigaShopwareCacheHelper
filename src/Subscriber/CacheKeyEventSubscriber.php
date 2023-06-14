<?php declare(strict_types=1);

namespace DigaShopwareCacheHelper\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\System\Country\Event\CountryRouteCacheTagsEvent;
use Shopware\Core\Content\Sitemap\Event\SitemapRouteCacheTagsEvent;
use Shopware\Core\System\Currency\Event\CurrencyRouteCacheTagsEvent;
use Shopware\Core\System\Language\Event\LanguageRouteCacheTagsEvent;
use Shopware\Core\Content\Category\Event\CategoryRouteCacheTagsEvent;
use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheTagsEvent;
use Shopware\Core\Content\Category\Event\NavigationRouteCacheTagsEvent;
use Shopware\Core\System\Country\Event\CountryStateRouteCacheTagsEvent;
use Shopware\Core\System\Salutation\Event\SalutationRouteCacheTagsEvent;
use Shopware\Core\Content\Product\Events\CrossSellingRouteCacheTagsEvent;
use Shopware\Core\Checkout\Payment\Event\PaymentMethodRouteCacheTagsEvent;
use Shopware\Core\Content\Product\Events\ProductDetailRouteCacheTagsEvent;
use Shopware\Core\Content\Product\Events\ProductSearchRouteCacheTagsEvent;
use Shopware\Core\Content\LandingPage\Event\LandingPageRouteCacheTagsEvent;
use Shopware\Core\Content\Product\Events\ProductListingRouteCacheTagsEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestRouteCacheTagsEvent;
use Shopware\Core\Checkout\Shipping\Event\ShippingMethodRouteCacheTagsEvent;

class CacheKeyEventSubscriber implements EventSubscriberInterface
{
    /**
    * @var LoggerInterface
    */
    private $logger;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(LoggerInterface $logger, SystemConfigService $systemConfigService)
    {
        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
    }

    public static function getSubscribedEvents(): array 
    {
        return [                       
            PaymentMethodRouteCacheTagsEvent::class => 'onCacheTags',
            ShippingMethodRouteCacheTagsEvent::class => 'onCacheTags',
            CategoryRouteCacheTagsEvent::class => 'onCacheTags',
            NavigationRouteCacheTagsEvent::class => 'onCacheTags',
            LandingPageRouteCacheTagsEvent::class => 'onCacheTags',
            CrossSellingRouteCacheTagsEvent::class => 'onCacheTags',
            ProductDetailRouteCacheTagsEvent::class => 'onCacheTags',
            ProductListingRouteCacheTagsEvent::class => 'onCacheTags',
            ProductSearchRouteCacheTagsEvent::class => 'onCacheTags',
            ProductSuggestRouteCacheTagsEvent::class => 'onCacheTags',
            SitemapRouteCacheTagsEvent::class => 'onCacheTags',
            StoreApiRouteCacheTagsEvent::class => 'onCacheTags',
            CountryRouteCacheTagsEvent::class => 'onCacheTags',
            CountryStateRouteCacheTagsEvent::class => 'onCacheTags',
            CurrencyRouteCacheTagsEvent::class => 'onCacheTags',
            LanguageRouteCacheTagsEvent::class => 'onCacheTags',
            SalutationRouteCacheTagsEvent::class => 'onCacheTags'
        ];
    }

    public function onCacheTags(StoreApiRouteCacheTagsEvent $event): void
    {        
        try {

            $selectedCacheTagEvents = $this->systemConfigService->get('DigaShopwareCacheHelper.config.selectedCacheTagEvents');
            $parts = explode('\\', get_class($event));
            $eventClass = array_pop($parts);

            // if $eventClass exist in $selectedCacheTagEvents array 
            if(is_array($selectedCacheTagEvents) && !in_array($eventClass, $selectedCacheTagEvents)) {
                return;
            }

            $tags = $event->getTags();
            $requestUri = $event->getRequest()->getRequestUri();
            
            $this->logger->info('CacheTagsEvent - Tags: ' . json_encode($tags) . ' RequestUri: '. $requestUri);

        } catch (\Throwable $th) {       
            $this->logger->error( $th->getMessage());
        }
    }
}