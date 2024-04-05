<?php

declare(strict_types=1);

namespace DigaShopwareCacheHelper\Subscriber;

use DigaShopwareCacheHelper\Service\DigaLoggerFactoryService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\System\Country\Event\CountryRouteCacheKeyEvent;
use Shopware\Core\Content\Sitemap\Event\SitemapRouteCacheKeyEvent;
use Shopware\Core\System\Currency\Event\CurrencyRouteCacheKeyEvent;
use Shopware\Core\System\Language\Event\LanguageRouteCacheKeyEvent;
use Shopware\Core\Content\Category\Event\CategoryRouteCacheKeyEvent;
use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent;
use Shopware\Core\Content\Category\Event\NavigationRouteCacheKeyEvent;
use Shopware\Core\System\Country\Event\CountryStateRouteCacheKeyEvent;
use Shopware\Core\System\Salutation\Event\SalutationRouteCacheKeyEvent;
use Shopware\Core\Content\Product\Events\CrossSellingRouteCacheKeyEvent;
use Shopware\Core\Checkout\Payment\Event\PaymentMethodRouteCacheKeyEvent;
use Shopware\Core\Content\Product\Events\ProductDetailRouteCacheKeyEvent;
use Shopware\Core\Content\Product\Events\ProductSearchRouteCacheKeyEvent;
use Shopware\Core\Content\LandingPage\Event\LandingPageRouteCacheKeyEvent;
use Shopware\Core\Content\Product\Events\ProductListingRouteCacheKeyEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestRouteCacheKeyEvent;
use Shopware\Core\Checkout\Shipping\Event\ShippingMethodRouteCacheKeyEvent;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;

class CacheKeyEventSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly DigaLoggerFactoryService $logger, private readonly SystemConfigService $systemConfigService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentMethodRouteCacheKeyEvent::class => 'onCacheKey',
            ShippingMethodRouteCacheKeyEvent::class => 'onCacheKey',
            CategoryRouteCacheKeyEvent::class => 'onCacheKey',
            NavigationRouteCacheKeyEvent::class => 'onCacheKey',
            LandingPageRouteCacheKeyEvent::class => 'onCacheKey',
            CrossSellingRouteCacheKeyEvent::class => 'onCacheKey',
            ProductDetailRouteCacheKeyEvent::class => 'onCacheKey',
            ProductListingRouteCacheKeyEvent::class => 'onCacheKey',
            ProductSearchRouteCacheKeyEvent::class => 'onCacheKey',
            ProductSuggestRouteCacheKeyEvent::class => 'onCacheKey',
            SitemapRouteCacheKeyEvent::class => 'onCacheKey',
            CountryRouteCacheKeyEvent::class => 'onCacheKey',
            CountryStateRouteCacheKeyEvent::class => 'onCacheKey',
            CurrencyRouteCacheKeyEvent::class => 'onCacheKey',
            LanguageRouteCacheKeyEvent::class => 'onCacheKey',
            SalutationRouteCacheKeyEvent::class => 'onCacheKey',
            // StoreApiRouteCacheKeyEvent::class => 'onCacheKey'
        ];
    }

    public function onCacheKey(StoreApiRouteCacheKeyEvent $event): void
    {
        try {
            $selectedCacheTagEvents = $this->systemConfigService->get('DigaShopwareCacheHelper.config.selectedCacheTagEvents');
            $parts = explode('\\', $event::class);
            $eventClass = array_pop($parts);

            // if $eventClass exist in $selectedCacheTagEvents array
            if (is_array($selectedCacheTagEvents) && !in_array($eventClass, $selectedCacheTagEvents)) {
                return;
            }

            $parts = $event->getParts();
            $key = '';

            if ($event instanceof StoreApiRouteCacheKeyEvent) {
                $key = 'payment-method-route-' . $event->getContext()->getSalesChannelId(). '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
            }

            if ($event instanceof PaymentMethodRouteCacheKeyEvent) {
                $key = 'payment-method-route-' . $event->getContext()->getSalesChannelId(). '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
            }

            if ($event instanceof ShippingMethodRouteCacheKeyEvent) {
                $key = 'shipping-method-route-' . $event->getContext()->getSalesChannelId(). '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
            }

            if ($event instanceof CategoryRouteCacheKeyEvent) {
                $key = 'category-route-' . $event->getNavigationId(). '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
            }

            if ($event instanceof NavigationRouteCacheKeyEvent) {
                $key = 'navigation-route-' . $event->getActive(). '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
            }

            if ($event instanceof LandingPageRouteCacheKeyEvent) {
                $key = 'landing-page-route-' . $event->getLandingPageId() . '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
            }

            if ($event instanceof CrossSellingRouteCacheKeyEvent) {
                $key = 'cross-selling-route-' . $event->getProductId() . '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
            }

            if ($event instanceof ProductDetailRouteCacheKeyEvent) {
                $key = 'product-detail-route-' . 'prodid-should-be-here' . '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
            }

            if ($event instanceof ProductListingRouteCacheKeyEvent) {
                $key = 'product-listing-route-' . $event->getCategoryId() . '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
            }

            if ($event instanceof ProductSearchRouteCacheKeyEvent) {
                $key = 'product-search-route' . '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
            }

            if ($event instanceof ProductSuggestRouteCacheKeyEvent) {
                $key = 'product-suggest-route' . '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
            }

            if ($event instanceof SitemapRouteCacheKeyEvent) {
                $key = 'sitemap-route-' . $event->getContext()->getSalesChannelId(). '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
            }

            if ($event instanceof CountryRouteCacheKeyEvent) {
                $key = 'country-route-' . $event->getContext()->getSalesChannelId(). '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
            }

            if ($event instanceof CountryStateRouteCacheKeyEvent) {
                $key = 'country-state-route-' . 'countryid-should-be-here' . '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
            }

            if ($event instanceof CurrencyRouteCacheKeyEvent) {
                $key = 'currency-route-' . $event->getContext()->getSalesChannelId(). '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
            }

            if ($event instanceof LanguageRouteCacheKeyEvent) {
                $key = 'language-route-' . $event->getContext()->getSalesChannelId(). '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
            }

            if ($event instanceof SalutationRouteCacheKeyEvent) {
                $key = 'salutation-route' . '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
            }

            $requestUri = $event->getRequest()->getRequestUri();

            $this->logger->info($eventClass . ' | '. $requestUri .' | ' . $key . ' | '. json_encode($parts));
        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }
}
