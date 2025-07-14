#
# Makefile
#

.PHONY: help
.DEFAULT_GOAL := help

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

# ------------------------------------------------------------------------------------------------------------

prod: ## Installs all production dependencies
	composer install --no-dev

dev: ## Installs all dev dependencies
	composer install

test: ## Starts all Tests
	php ./vendor/bin/phpunit --configuration=./phpunit.xml

validate: ## Validation process for Plugins
	@make stan

clean: ## Cleans all dependencies
	rm -rf ./vendor
	rm -rf ./src/Resources/app/administration/node_modules
	rm -rf ./src/Resources/app/storefront/node_modules

build: ## Installs the plugin, and builds the artifacts using the Shopware build commands (requires Shopware)
	cd /var/www/html && php bin/console plugin:refresh
	cd /var/www/html && php bin/console plugin:install DigaShopwareCacheHelper --activate | true
	cd /var/www/html && php bin/console plugin:refresh
	cd /var/www/html && ./bin/build-js.sh
	cd /var/www/html && php bin/console theme:refresh
	touch tempversion.txt
	../../../bin/console --version > tempversion.txt
	sed -i 's/[^0-9.]*//g' ./tempversion.txt
	sed -i '1s/^/- Last compiled on SW /' ./tempversion.txt
	sed -i '/## Supported SW Version:/{n; s/.*//}' ./README.md
	sed -i -e '/## Supported SW Version:/r tempversion.txt' ./README.md
	rm -rf ./tempversion.txt

zip: ## create zip
	@make clean
	$(eval VERS := $(shell cat composer.json | jq '.version'))
	@echo Create zip for version: $(VERS)
	rm -rf ./artifacts/DigaShopwareCacheHelper* && mkdir -p ./artifacts
	cd .. && zip -qq -r -0 DigaShopwareCacheHelper/artifacts/DigaShopwareCacheHelper_$(VERS).zip DigaShopwareCacheHelper/ -x '*.editorconfig' '*.git*' '*.reports*' '*/.idea*' '*/tests*' '*/node_modules*' '*/makefile' '*.DS_Store' '*/switch-composer.php' '*/phpunit.xml' '*/.infection.json' '*/phpunit.autoload.php' '*/.phpstan*' '*/.php_cs.php' '*/phpinsights.php' '*/artifacts*' '*.twig-cs-fixer*'

release: ## Builds a production version and creates a ZIP file in <PLUGIN_ROOT>/artifacts
	@make clean
	@make build
	@make zip

twig-cs-fixer: ## Twig coding standard validator
	vendor/bin/twig-cs-fixer lint

style-twig: ## Formats Twig
	vendor/bin/twig-cs-fixer lint --fix

stan: ## Starts the PHPStan Analyser
	php ./vendor/bin/phpstan --memory-limit=1G analyse .