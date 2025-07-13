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
use Shopware\Core\Framework\Util\Json;

class CacheKeyEventSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly DigaLoggerFactoryService $logger, private readonly SystemConfigService $systemConfigService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentMethodRouteCacheKeyEvent::class => 'onPaymentMethodRouteCacheKeyEvent',
            StoreApiRouteCacheKeyEvent::class => 'onStoreApiRouteCacheKeyEvent',
            ShippingMethodRouteCacheKeyEvent::class => 'onShippingMethodRouteCacheKeyEvent',
            CategoryRouteCacheKeyEvent::class => 'onCategoryRouteCacheKeyEvent',
            NavigationRouteCacheKeyEvent::class => 'onNavigationRouteCacheKeyEvent',
            LandingPageRouteCacheKeyEvent::class => 'onLandingPageRouteCacheKeyEvent',
            CrossSellingRouteCacheKeyEvent::class => 'onCrossSellingRouteCacheKeyEvent',
            ProductDetailRouteCacheKeyEvent::class => 'onProductDetailRouteCacheKeyEvent',
            ProductListingRouteCacheKeyEvent::class => 'onProductListingRouteCacheKeyEvent',
            ProductSearchRouteCacheKeyEvent::class => 'onProductSearchRouteCacheKeyEvent',
            ProductSuggestRouteCacheKeyEvent::class => 'onProductSuggestRouteCacheKeyEvent',
            SitemapRouteCacheKeyEvent::class => 'onSitemapRouteCacheKeyEvent',
            CountryRouteCacheKeyEvent::class => 'onCountryRouteCacheKeyEvent',
            CountryStateRouteCacheKeyEvent::class => 'onCountryStateRouteCacheKeyEvent',
            CurrencyRouteCacheKeyEvent::class => 'onCurrencyRouteCacheKeyEvent',
            LanguageRouteCacheKeyEvent::class => 'onLanguageRouteCacheKeyEvent',
            SalutationRouteCacheKeyEvent::class => 'onSalutationRouteCacheKeyEvent',
        ];
    }

    public function onPaymentMethodRouteCacheKeyEvent(PaymentMethodRouteCacheKeyEvent $event): void
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

            if ($event instanceof PaymentMethodRouteCacheKeyEvent) {
                $key = 'payment-method-route-' . $event->getContext()->getSalesChannelId(). '-' . md5(Json::encode($event->getParts()));
            }

            $requestUri = $event->getRequest()->getRequestUri();
            $logFullUri = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logFullUri');
            if ($logFullUri) {
                $requestUri = $event->getRequest()->getUri();
            }               

            $this->logger->info($eventClass . ' | '. $requestUri .' | ' . $key . ' | '. json_encode($parts));

        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }

    public function onStoreApiRouteCacheKeyEvent(StoreApiRouteCacheKeyEvent $event): void
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
                $key = 'payment-method-route-' . $event->getContext()->getSalesChannelId(). '-' . md5(Json::encode($event->getParts()));
            }

            $requestUri = $event->getRequest()->getRequestUri();
            $logFullUri = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logFullUri');
            if ($logFullUri) {
                $requestUri = $event->getRequest()->getUri();
            }               

            $this->logger->info($eventClass . ' | '. $requestUri .' | ' . $key . ' | '. json_encode($parts));

        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }

    public function onShippingMethodRouteCacheKeyEvent(ShippingMethodRouteCacheKeyEvent $event): void
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

            if ($event instanceof ShippingMethodRouteCacheKeyEvent) {
                $key = 'shipping-method-route-' . $event->getContext()->getSalesChannelId(). '-' . md5(Json::encode($event->getParts()));
            }

            $requestUri = $event->getRequest()->getRequestUri();
            $logFullUri = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logFullUri');
            if ($logFullUri) {
                $requestUri = $event->getRequest()->getUri();
            }               

            $this->logger->info($eventClass . ' | '. $requestUri .' | ' . $key . ' | '. json_encode($parts));

        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }

    public function onCategoryRouteCacheKeyEvent(CategoryRouteCacheKeyEvent $event): void
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

            if ($event instanceof CategoryRouteCacheKeyEvent) {
                $key = 'category-route-' . $event->getNavigationId(). '-' . md5(Json::encode($event->getParts()));
            }

            $requestUri = $event->getRequest()->getRequestUri();
            $logFullUri = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logFullUri');
            if ($logFullUri) {
                $requestUri = $event->getRequest()->getUri();
            }               

            $this->logger->info($eventClass . ' | '. $requestUri .' | ' . $key . ' | '. json_encode($parts));

        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }

    public function onNavigationRouteCacheKeyEvent(NavigationRouteCacheKeyEvent $event): void
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

            if ($event instanceof NavigationRouteCacheKeyEvent) {
                $key = 'navigation-route-' . $event->getActive(). '-' . md5(Json::encode($event->getParts()));
            }

            $requestUri = $event->getRequest()->getRequestUri();
            $logFullUri = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logFullUri');
            if ($logFullUri) {
                $requestUri = $event->getRequest()->getUri();
            }               

            $this->logger->info($eventClass . ' | '. $requestUri .' | ' . $key . ' | '. json_encode($parts));

        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }

    public function onLandingPageRouteCacheKeyEvent(LandingPageRouteCacheKeyEvent $event): void
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

            if ($event instanceof LandingPageRouteCacheKeyEvent) {
                $key = 'landing-page-route-' . $event->getLandingPageId() . '-' . md5(Json::encode($event->getParts()));
            }

            $requestUri = $event->getRequest()->getRequestUri();
            $logFullUri = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logFullUri');
            if ($logFullUri) {
                $requestUri = $event->getRequest()->getUri();
            }               

            $this->logger->info($eventClass . ' | '. $requestUri .' | ' . $key . ' | '. json_encode($parts));

        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }

    public function onCrossSellingRouteCacheKeyEvent(CrossSellingRouteCacheKeyEvent $event): void
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

            if ($event instanceof CrossSellingRouteCacheKeyEvent) {
                $key = 'cross-selling-route-' . $event->getProductId() . '-' . md5(Json::encode($event->getParts()));
            }

            $requestUri = $event->getRequest()->getRequestUri();
            $logFullUri = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logFullUri');
            if ($logFullUri) {
                $requestUri = $event->getRequest()->getUri();
            }               

            $this->logger->info($eventClass . ' | '. $requestUri .' | ' . $key . ' | '. json_encode($parts));

        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }

    public function onProductDetailRouteCacheKeyEvent(ProductDetailRouteCacheKeyEvent $event): void
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

            if ($event instanceof ProductDetailRouteCacheKeyEvent) {
                $key = 'product-detail-route-' . 'prodid-should-be-here' . '-' . md5(Json::encode($event->getParts()));
            }

            $requestUri = $event->getRequest()->getRequestUri();
            $logFullUri = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logFullUri');
            if ($logFullUri) {
                $requestUri = $event->getRequest()->getUri();
            }               

            $this->logger->info($eventClass . ' | '. $requestUri .' | ' . $key . ' | '. json_encode($parts));

        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }    

    public function onProductListingRouteCacheKeyEvent(ProductListingRouteCacheKeyEvent $event): void
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

            if ($event instanceof ProductListingRouteCacheKeyEvent) {
                $key = 'product-listing-route-' . $event->getCategoryId() . '-' . md5(Json::encode($event->getParts()));
            }

            $requestUri = $event->getRequest()->getRequestUri();
            $logFullUri = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logFullUri');
            if ($logFullUri) {
                $requestUri = $event->getRequest()->getUri();
            }               

            $this->logger->info($eventClass . ' | '. $requestUri .' | ' . $key . ' | '. json_encode($parts));

        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }    

    public function onProductSearchRouteCacheKeyEvent(ProductSearchRouteCacheKeyEvent $event): void
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

            if ($event instanceof ProductSearchRouteCacheKeyEvent) {
                $key = 'product-search-route' . '-' . md5(Json::encode($event->getParts()));
            }

            $requestUri = $event->getRequest()->getRequestUri();
            $logFullUri = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logFullUri');
            if ($logFullUri) {
                $requestUri = $event->getRequest()->getUri();
            }               

            $this->logger->info($eventClass . ' | '. $requestUri .' | ' . $key . ' | '. json_encode($parts));

        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }    

    public function onProductSuggestRouteCacheKeyEvent(ProductSuggestRouteCacheKeyEvent $event): void
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

            if ($event instanceof ProductSuggestRouteCacheKeyEvent) {
                $key = 'product-suggest-route' . '-' . md5(Json::encode($event->getParts()));
            }

            $requestUri = $event->getRequest()->getRequestUri();
            $logFullUri = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logFullUri');
            if ($logFullUri) {
                $requestUri = $event->getRequest()->getUri();
            }               

            $this->logger->info($eventClass . ' | '. $requestUri .' | ' . $key . ' | '. json_encode($parts));

        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }    

    public function onSitemapRouteCacheKeyEvent(SitemapRouteCacheKeyEvent $event): void
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

            if ($event instanceof SitemapRouteCacheKeyEvent) {
                $key = 'sitemap-route-' . $event->getContext()->getSalesChannelId(). '-' . md5(Json::encode($event->getParts()));
            }

            $requestUri = $event->getRequest()->getRequestUri();
            $logFullUri = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logFullUri');
            if ($logFullUri) {
                $requestUri = $event->getRequest()->getUri();
            }               

            $this->logger->info($eventClass . ' | '. $requestUri .' | ' . $key . ' | '. json_encode($parts));

        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }    

    public function onCountryRouteCacheKeyEvent(CountryRouteCacheKeyEvent $event): void
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

            if ($event instanceof CountryRouteCacheKeyEvent) {
                $key = 'country-route-' . $event->getContext()->getSalesChannelId(). '-' . md5(Json::encode($event->getParts()));
            }

            $requestUri = $event->getRequest()->getRequestUri();
            $logFullUri = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logFullUri');
            if ($logFullUri) {
                $requestUri = $event->getRequest()->getUri();
            }               

            $this->logger->info($eventClass . ' | '. $requestUri .' | ' . $key . ' | '. json_encode($parts));

        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }    

    public function onCountryStateRouteCacheKeyEvent(CountryStateRouteCacheKeyEvent $event): void
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

            if ($event instanceof CountryStateRouteCacheKeyEvent) {
                $key = 'country-state-route-' . 'countryid-should-be-here' . '-' . md5(Json::encode($event->getParts()));
            }
            $requestUri = $event->getRequest()->getRequestUri();
            $logFullUri = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logFullUri');
            if ($logFullUri) {
                $requestUri = $event->getRequest()->getUri();
            }               

            $this->logger->info($eventClass . ' | '. $requestUri .' | ' . $key . ' | '. json_encode($parts));

        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }    

    public function onCurrencyRouteCacheKeyEvent(CurrencyRouteCacheKeyEvent $event): void
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

            if ($event instanceof CurrencyRouteCacheKeyEvent) {
                $key = 'currency-route-' . $event->getContext()->getSalesChannelId(). '-' . md5(Json::encode($event->getParts()));
            }

            $requestUri = $event->getRequest()->getRequestUri();
            $logFullUri = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logFullUri');
            if ($logFullUri) {
                $requestUri = $event->getRequest()->getUri();
            }               

            $this->logger->info($eventClass . ' | '. $requestUri .' | ' . $key . ' | '. json_encode($parts));

        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }    

    public function onLanguageRouteCacheKeyEvent(LanguageRouteCacheKeyEvent $event): void
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

            if ($event instanceof LanguageRouteCacheKeyEvent) {
                $key = 'language-route-' . $event->getContext()->getSalesChannelId(). '-' . md5(Json::encode($event->getParts()));
            }
            $requestUri = $event->getRequest()->getRequestUri();
            $logFullUri = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logFullUri');
            if ($logFullUri) {
                $requestUri = $event->getRequest()->getUri();
            }               

            $this->logger->info($eventClass . ' | '. $requestUri .' | ' . $key . ' | '. json_encode($parts));

        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }    

    public function onSalutationRouteCacheKeyEvent(SalutationRouteCacheKeyEvent $event): void
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

            if ($event instanceof SalutationRouteCacheKeyEvent) {
                $key = 'salutation-route' . '-' . md5(Json::encode($event->getParts()));
            }
            $requestUri = $event->getRequest()->getRequestUri();
            $logFullUri = $this->systemConfigService->get('DigaShopwareCacheHelper.config.logFullUri');
            if ($logFullUri) {
                $requestUri = $event->getRequest()->getUri();
            }               

            $this->logger->info($eventClass . ' | '. $requestUri .' | ' . $key . ' | '. json_encode($parts));

        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
        }
    }
}
