# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Added
### Changed
### Removed

## [3.0.0] - 04.04.2024
- Added support for Shopware 6.6
### Changed
- Removed deprecated http cache warmup commands, WarmupMessage scheduled task and handler

## [2.1.0] - 04.04.2024
- added option to configuration for changing logging output
- added DigaLoggerFactory

## [2.0.2] - 26.03.2024
- update workflows
- fix stan issues

## [2.0.1] - 06.12.2023
- Changed logging to use Monolog channel

## [2.0.0] - 21.11.2023
- compatibility fixes for SW 6.5.7.1

## [1.0.3] - 23.06.2023
- refactor http cache key generated to see which cookies are used and what is the resulting cache-key

## [1.0.2] - 23.06.2023
- add option to log which seo urls should be warmed up by cache warmer
- add parameter so we can warmup just a specific saleschannel
- change logger row structure to Event | URL | ItemKey | data for better analyzing the data

## [1.0.2] - 17.06.2023
- add command to be able to warmup cache just for active domains and selected warmer (category or products)
- add warmup message handler to log which urls are warmed up during http:cache:warm:up  

## [1.0.2] - 16.06.2023
- add config to disable tags logging on cache item generated
- add ttl and maxage output
- onCacheTags log the class type generating the tags
- add StoreApiRouteCacheKeyEvent logging

## [1.0.1] - 15.06.2023
- add more configuration ways so user can decide what he really wants to log

## [1.0.0] - 21.05.2023
- initial version

[Unreleased]: https://github.com/ditegra-GmbH/DigaShopwareCacheHelper 
[1.0.0]: https://github.com/ditegra-GmbH/DigaShopwareCacheHelper/releases/tag/v1.0.0
[1.0.1]: https://github.com/ditegra-GmbH/DigaShopwareCacheHelper/releases/tag/v1.0.1
[1.0.2]: https://github.com/ditegra-GmbH/DigaShopwareCacheHelper/releases/tag/v1.0.2
