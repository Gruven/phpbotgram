.PHONY: test stan lint fix regenerate coverage coverage-gate docs-api docs-tools-fetch

PHPDOC_VERSION := v3.10.0
PHPDOC_PHAR := build/tools/phpDocumentor-$(PHPDOC_VERSION).phar
PHPDOC_URL := https://github.com/phpDocumentor/phpDocumentor/releases/download/$(PHPDOC_VERSION)/phpDocumentor.phar

test:
	vendor/bin/phpunit

stan:
	vendor/bin/phpstan analyze

lint:
	vendor/bin/php-cs-fixer fix --dry-run --diff

fix:
	vendor/bin/php-cs-fixer fix

regenerate:
	php tools/generator/bin/generate.php

coverage:
	XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-clover=build/coverage/clover.xml

coverage-gate: coverage
	php scripts/coverage-gate.php build/coverage/clover.xml

# Fetch the phpDocumentor phar into build/tools/ (gitignored). Honoured by
# `docs-api` as a one-shot bootstrap so contributors don't ship the 32MB
# binary in the repo. NO_PROXY='*' bypasses a corporate proxy if present.
docs-tools-fetch:
	@mkdir -p build/tools
	@if [ ! -f "$(PHPDOC_PHAR)" ]; then \
	  echo "Fetching phpDocumentor from $(PHPDOC_URL) -> $(PHPDOC_PHAR)"; \
	  NO_PROXY='*' no_proxy='*' curl -fsSL -o "$(PHPDOC_PHAR)" "$(PHPDOC_URL)"; \
	fi

# Build the API documentation site under build/docs/api/. Use phpdoc.dist.xml
# at the repo root for configuration. Output is gitignored.
docs-api: docs-tools-fetch
	php $(PHPDOC_PHAR) -c phpdoc.dist.xml
