# Development

The plugin lives inside a Shopware installation at `custom/plugins/DigaShopwareCacheHelper`. All commands run from the plugin directory; `make help` lists the targets.

## Setup

```bash
make dev    # composer install incl. PHPUnit, PHPStan, php-cs-fixer
```

`make dev` installs a complete `shopware/core` into the plugin's own `vendor/`. That is needed for tests and static analysis, but must not be loaded by the host shop. Move `vendor/` away (or run `make clean`) before you install or activate the plugin in the shop.

## Tests

```bash
make test
php ./vendor/bin/phpunit --configuration=./phpunit.xml --filter InvalidationOriginRecordingTest
```

- Unit tests live in `tests/Unit` and run against the plugin's `vendor/autoload.php`. They need no Shopware kernel and no database.
- `InvalidationOriginRecordingTest` runs a real `CacheInvalidator` with the plugin's storage decorator and subscriber wired in, so stack frames are exactly what Shopware produces.
- Fixture plugins for plugin detection are in `tests/Fixture` (namespace `DigaTestFixture\`, via `autoload-dev`). The namespace must stay outside `DigaShopwareCacheHelper\`, because the resolver skips the plugin's own frames.

## Static analysis

```bash
make stan   # alias: make validate
```

PHPStan level 5 (`phpstan.neon`). The makefile passes `.`, so it analyses `src/` and `tests/`; `src/Resources` and `vendor` are excluded. CI runs this on every pull request.

## Trying it in the shop

```bash
mv vendor ../.diga-vendor-bak                     # keep the plugin's vendor out of the shop
cd /var/www/html
bin/console plugin:refresh
bin/console plugin:install --activate DigaShopwareCacheHelper
bin/console cache:clear
bin/console system:config:set DigaShopwareCacheHelper.config.loggingOutput cachehelperlog
bin/console system:config:set DigaShopwareCacheHelper.config.logInvalidationOrigin true
bin/console cache:clear:delayed                   # purge deferred tags now instead of waiting for the task
tail -f var/log/diga_cache_helper-dev-*.log
```

`bin/console debug:container 'Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\AbstractInvalidatorStorage'` should point to `TracingInvalidatorStorage`.

Move `../.diga-vendor-bak` back to `vendor` afterwards.

## Build and release

| Target | What it does |
| --- | --- |
| `make build` | Refreshes, installs and activates the plugin in the host shop, builds the admin JS (`bin/build-js.sh`), and writes the current Shopware version into the README's "Supported SW Version" line |
| `make zip` | Runs `make clean`, then creates `artifacts/DigaShopwareCacheHelper_<version>.zip`. Excludes tests, makefile, phpunit.xml and dotfiles. Needs `jq` |
| `make release` | `clean` + `build` + `zip` |
| `make clean` | Removes `vendor` and admin/storefront `node_modules` |

To release:

1. Bump `version` in `composer.json`.
2. Move the entries under `## [Unreleased]` in `CHANGELOG.md` to a new `## [x.y.z] - DD.MM.YYYY` section. The release pipeline extracts exactly this heading format for the release notes.
3. Run the *Build validate & create release* workflow.

## CI

All workflows run in `ghcr.io/friendsofshopware/platform-plugin-dev:v6.7.0` with `shopware-cli`:

| Workflow | Trigger | Steps |
| --- | --- | --- |
| `pr-build-pipe.yml` | pull requests, manual | `make dev`, `make validate`, `shopware-cli extension build`, `make zip`, `shopware-cli extension validate`, upload artifact |
| `build-staging-pipe.yml` | manual | as above, then copy the zip to the staging host and install it there |
| `release-pipe.yml` | manual | as above, then create the GitHub release `v<version>` with the CHANGELOG section as notes |

CI does not run `make test` yet.

## Adding a new log option

1. Add an `input-field` to `src/Resources/config/config.xml` with English and German `label` and `helpText`.
2. Read it in the handler via `$this->systemConfigService->get('DigaShopwareCacheHelper.config.<name>')`.
3. Keep the four-field `Event | URI | key | details` line format, so existing filters keep working.
4. Document it in [configuration.md](configuration.md) and [log-reference.md](log-reference.md).
