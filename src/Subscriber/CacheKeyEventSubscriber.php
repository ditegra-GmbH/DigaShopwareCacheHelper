<?php declare(strict_types=1);

namespace DigaShopwareCacheHelper\Subscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Content\Category\Event\CategoryRouteCacheTagsEvent;
use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheTagsEvent;
use Shopware\Core\Content\Category\Event\NavigationRouteCacheTagsEvent;
use Shopware\Core\Content\Product\Events\ProductDetailRouteCacheTagsEvent;
use Shopware\Core\Content\LandingPage\Event\LandingPageRouteCacheTagsEvent;

class CacheKeyEventSubscriber implements EventSubscriberInterface
{
    /**
    * @var LoggerInterface
    */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array 
    {
        return [
            CategoryRouteCacheTagsEvent::class => 'onCacheTags',
            NavigationRouteCacheTagsEvent::class => 'onCacheTags',
            LandingPageRouteCacheTagsEvent::class => 'onCacheTags',
            ProductDetailRouteCacheTagsEvent::class => 'onCacheTags',
            ProductListingRouteCacheTagsEvent::class => 'onCacheTags'
            // ProductSearchRouteCacheTagsEvent::class => 'onCacheTags',
            // CrossSellingRouteCacheTagsEvent::class => 'onCacheTags',
            // PaymentMethodRouteCacheTagsEvent::class => 'onCacheTags',
            // ShippingMethodRouteCacheTagsEvent::class => 'onCacheTags',
            // ProductSuggestRouteCacheTagsEvent::class => 'onCacheTags',
            // SitemapRouteCacheTagsEvent::class => 'onCacheTags',
            // CountryRouteCacheTagsEvent::class => 'onCacheTags',
            // CountryStateRouteCacheTagsEvent::class => 'onCacheTags',
            // CurrencyRouteCacheTagsEvent::class => 'onCacheTags',
            // LanguageRouteCacheTagsEvent::class => 'onCacheTags',
            // SalutationRouteCacheTagsEvent::class => 'onCacheTags',
            //TODO: what aout all the other page types and events?
            // all which extends => extends StoreApiRouteCacheTagsEvent
        ];
    }

    public function onCacheTags(StoreApiRouteCacheTagsEvent $event): void
    {        
        try {
            
            // $event->getContext()->getContext()->hasExtension("activePromotionsInfoListing")
            $tags = $event->getTags();
            $requestUri = $event->getRequest()->getRequestUri();
            
            $this->logger->info('RequestUri: '. $requestUri .' CacheKeyEventSubscriber: onCacheTags: ' . json_encode($tags));

        } catch (\Throwable $th) {       
            $this->logger->error( $th->getMessage());
        } 

    }
}